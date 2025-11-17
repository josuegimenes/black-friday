<?php
$catalog = require __DIR__ . '/data/catalog.php';
$defaultSlug = array_key_first($catalog);
$slug = $_GET['slug'] ?? $defaultSlug;

if (!array_key_exists($slug, $catalog)) {
    http_response_code(404);
    echo 'Categoria nao encontrada';
    exit;
}

$category = $catalog[$slug];
$products = $category['products'];
$pricingOverrides = is_file(__DIR__ . '/config/product_pricing.php')
    ? require __DIR__ . '/config/product_pricing.php'
    : [];

$categoryIllustrations = [
    'vestidos-longos' => 'vestidos-longos-white.png',
    'vestidos-curtos' => 'vestidos-curtos-white.png',
    'conjuntos' => 'conjuntos-white.png',
    'blusas' => 'blusas-white.png',
    'saias' => 'saias-white.png',
    'calcas' => 'calcas-white.png',
    'shorts' => 'shorts-white.png',
    'macaquinhos' => 'macaquinhos-white.png',
];

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

function ensure_utf8(string $value): string
{
    if (!mb_detect_encoding($value, 'UTF-8', true)) {
        $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
        if ($converted !== false) {
            return $converted;
        }
    }
    return $value;
}

function slugify(string $value): string
{
    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $transliterated = strtolower($transliterated);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $transliterated);
    return trim($slug ?? '', '-');
}

function relative_asset_path(string $absolutePath): string
{
    $base = rtrim(str_replace('\\', '/', __DIR__), '/') . '/';
    $normalized = str_replace('\\', '/', $absolutePath);
    if (str_starts_with($normalized, $base)) {
        return substr($normalized, strlen($base));
    }
    return $normalized;
}

function parse_descriptor(string $filePath): array
{
    $raw = @file_get_contents($filePath);
    if ($raw === false) {
        return [];
    }

    $raw = ensure_utf8($raw);
    $lines = preg_split('/\r\n|\r|\n/', $raw);

    $meta = [
        'name' => null,
        'fabric' => null,
        'colors' => [],
        'sizes_map' => [],
        'quantities' => [],
        'description' => '',
    ];

    $section = null;
    $descriptionParts = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($meta['name'] === null && $trimmed !== '') {
            $meta['name'] = $trimmed;
            continue;
        }

        if ($trimmed === '') {
            if ($section === 'description') {
                $descriptionParts[] = '';
            }
            continue;
        }

        if (stripos($trimmed, 'Tecido:') === 0) {
            $meta['fabric'] = trim(substr($trimmed, strlen('Tecido:')));
            $section = null;
            continue;
        }

        if (stripos($trimmed, 'Cores:') === 0) {
            $colorLine = trim(substr($trimmed, strlen('Cores:')));
            $meta['colors'] = array_values(array_filter(array_map('trim', preg_split('/[,;\/]+/', $colorLine))));
            $section = null;
            continue;
        }

        if (stripos($trimmed, 'Tamanhos') === 0) {
            $section = 'sizes';
            continue;
        }

        if (stripos($trimmed, 'Quantidade') === 0) {
            $section = 'quantities';
            continue;
        }

        if ($section === 'sizes') {
            if (preg_match('/^([A-Za-z0-9]+)\s*:\s*(.+)$/', $trimmed, $matches)) {
                $meta['sizes_map'][strtoupper($matches[1])] = trim($matches[2]);
            } else {
                $meta['sizes_map'][strtoupper($trimmed)] = null;
            }
            continue;
        }

        if ($section === 'quantities') {
            if (preg_match('/^(\d+)\s*:\s*([A-Za-z0-9]+)/', $trimmed, $matches)) {
                $meta['quantities'][] = sprintf('%s un tamanho %s', $matches[1], strtoupper($matches[2]));
            } else {
                $meta['quantities'][] = $trimmed;
            }
            continue;
        }

        $section = 'description';
        $descriptionParts[] = $trimmed;
    }

    $meta['description'] = trim(preg_replace("/\n{2,}/", "\n\n", implode("\n", $descriptionParts)));
    $meta['size_options'] = array_keys($meta['sizes_map']);

    return $meta;
}

function collect_gallery(string $directory): array
{
    $extensions = ['jpg', 'jpeg', 'png', 'webp', 'avif'];
    $pattern = $directory . '/*.{' . implode(',', $extensions) . '}';
    $files = glob($pattern, GLOB_BRACE);
    if (!$files) {
        return [];
    }
    sort($files, SORT_NATURAL);
    return array_map('relative_asset_path', $files);
}

function load_asset_products(string $slug, array $fallbackProducts, array $pricingOverrides): array
{
    $baseDir = __DIR__ . '/assets/img/' . $slug;
    if (!is_dir($baseDir)) {
        return [];
    }

    $directories = array_filter(glob($baseDir . '/*', GLOB_ONLYDIR));
    sort($directories, SORT_NATURAL);
    $products = [];
    $fallbackCount = count($fallbackProducts);

    foreach ($directories as $index => $dir) {
        $fallbackIndex = $fallbackCount > 0 ? min($index, $fallbackCount - 1) : null;
        $fallback = $fallbackIndex !== null ? $fallbackProducts[$fallbackIndex] : null;

        $descriptorFile = current(glob($dir . '/*.txt'));
        $meta = $descriptorFile ? parse_descriptor($descriptorFile) : [];
        $gallery = collect_gallery($dir);
        if (empty($gallery) && $fallback && !empty($fallback['thumb'])) {
            $gallery = [$fallback['thumb']];
        }
        if (empty($meta) && empty($gallery)) {
            continue;
        }

        $name = $meta['name'] ?? ($fallback['name'] ?? basename($dir));
        $nameSlug = slugify($name ?: basename($dir));
        $productId = $slug . '-' . $nameSlug . '-' . ($index + 1);
        $override = $pricingOverrides[$slug][$nameSlug] ?? [];

        $sizeOptions = !empty($meta['size_options']) ? $meta['size_options'] : ($fallback['sizes'] ?? []);
        $colorOptions = !empty($meta['colors']) ? $meta['colors'] : ($fallback['colors'] ?? []);

        if (empty($sizeOptions)) {
            $sizeOptions = ['Unico'];
        }

        if (empty($colorOptions)) {
            $colorOptions = ['Padrao'];
        }

        $products[] = [
            'id' => $productId,
            'name' => $name,
            'thumb' => $gallery[0] ?? ($fallback['thumb'] ?? ''),
            'gallery' => $gallery,
            'fabric' => $meta['fabric'] ?? null,
            'colors' => $colorOptions,
            'sizes' => $sizeOptions,
            'size_notes' => $meta['sizes_map'] ?? [],
            'quantities' => $meta['quantities'] ?? [],
            'description' => $meta['description'] ?: ($fallback['cover_copy'] ?? ''),
            'original_price' => $override['original_price'] ?? ($fallback['original_price'] ?? 0),
            'sale_price' => $override['sale_price'] ?? ($fallback['sale_price'] ?? 0),
        ];
    }

    return $products;
}

$mediaBaseDir = __DIR__ . '/assets/img/saias';

function asset_public_path(string $absolutePath): string
{
    $base = rtrim(str_replace('\\', '/', __DIR__), '/') . '/';
    $normalized = str_replace('\\', '/', $absolutePath);
    $relative = str_starts_with($normalized, $base) ? substr($normalized, strlen($base)) : $normalized;
    return implode('/', array_map('rawurlencode', explode('/', $relative)));
}

function parse_payment_lines(array $lines): array
{
    $payments = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (preg_match('/([0-9]+[,\\.][0-9]{2})/', $line, $m)) {
            $price = (float) str_replace(',', '.', $m[1]);
            $payments[] = ['label' => $line, 'price' => $price];
        } else {
            $payments[] = ['label' => $line, 'price' => null];
        }
    }
    return $payments;
}

function parse_sizes_options(?string $line): array
{
    if (!$line) return [];
    $options = [];
    if (preg_match_all('/([A-Za-z0-9]+)\s*-\s*([0-9 ]+)/u', $line, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $code = trim($match[1]);
            $numbers = preg_replace('/\s+/', '/', trim($match[2]));
            $options[] = [
                'value' => $code,
                'label' => sprintf('%s (%s)', $code, $numbers),
            ];
        }
    }
    return $options;
}

function build_saias_products(string $baseDir): array
{
    if (!is_dir($baseDir)) return [];

    $products = [];

    foreach (scandir($baseDir) as $productDir) {
        if ($productDir === '.' || $productDir === '..') continue;
        $productPath = $baseDir . '/' . $productDir;
        if (!is_dir($productPath)) continue;

        $descFile = $productPath . '/Descrição.md';
        $metaText = is_file($descFile) ? ensure_utf8((string)file_get_contents($descFile)) : '';
        $metaLines = preg_split('/\r\n|\r|\n/', $metaText) ?: [];
        $name = trim($metaLines[0] ?? $productDir);

        $fabric = null;
        $sizesLine = null;
        $infoLines = [];
        $measureLines = [];
        $paymentLines = [];
        $mode = null;

        foreach ($metaLines as $line) {
            $trim = trim($line);
            if (stripos($trim, 'Tecido:') === 0) {
                $fabric = trim(substr($trim, strlen('Tecido:')));
                continue;
            }
            if (stripos($trim, 'Tamanhos') === 0) {
                $sizesLine = trim(substr($trim, strlen('Tamanhos:')));
                continue;
            }
            if (stripos($trim, 'INFORMA') === 0) {
                $mode = 'info';
                continue;
            }
            if (stripos($trim, 'MEDIDAS') === 0) {
                $mode = 'measure';
                continue;
            }
            if (stripos($trim, 'FORMAS DE PAGAMENTO') === 0) {
                $mode = 'payment';
                continue;
            }

            if ($trim === '') continue;

            if ($mode === 'info') $infoLines[] = $trim;
            elseif ($mode === 'measure') $measureLines[] = $trim;
            elseif ($mode === 'payment') $paymentLines[] = $trim;
        }

        $payments = parse_payment_lines($paymentLines);
        $salePrice = $payments[0]['price'] ?? null;
        $originalPrice = $payments[1]['price'] ?? ($salePrice ? $salePrice * 1.2 : null);
        $sizeOptions = parse_sizes_options($sizesLine);

        $colors = [];
        foreach (scandir($productPath) as $colorDir) {
            if ($colorDir === '.' || $colorDir === '..') continue;
            $colorPath = $productPath . '/' . $colorDir;
            if (!is_dir($colorPath)) continue;
            if (stripos($colorDir, 'descri') === 0) continue;

            $gallery = [];
            $primary = null;
            $videos = [];

            foreach (scandir($colorPath) as $asset) {
                if ($asset === '.' || $asset === '..') continue;
                $assetPath = $colorPath . '/' . $asset;
                if (is_dir($assetPath) && strtolower($asset) === 'video') {
                    foreach (scandir($assetPath) as $vid) {
                        if ($vid === '.' || $vid === '..') continue;
                        $ext = strtolower(pathinfo($vid, PATHINFO_EXTENSION));
                        if (in_array($ext, ['mp4', 'mov'])) {
                            $videos[] = asset_public_path($assetPath . '/' . $vid);
                        }
                    }
                    continue;
                }
                if (is_file($assetPath)) {
                    $ext = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) continue;
                    $relative = asset_public_path($assetPath);
                    if (stripos($asset, 'principal') === 0) {
                        $primary = $relative;
                    } else {
                        $gallery[] = $relative;
                    }
                }
            }

            if (!$primary && $gallery) {
                $primary = $gallery[0];
            }

            $colors[] = [
                'label' => $colorDir,
                'slug' => slugify($colorDir),
                'primary' => $primary,
                'gallery' => $gallery,
                'videos' => $videos,
            ];
        }

        if (empty($colors)) continue;

        $products[] = [
            'id' => slugify($name ?: $productDir),
            'name' => $name ?: $productDir,
            'fabric' => $fabric,
            'sizes_line' => $sizesLine ?: '--',
            'sizes_parsed' => $sizeOptions,
            'info' => $infoLines,
            'measures' => $measureLines,
            'payments' => $payments,
            'original_price' => $originalPrice,
            'sale_price' => $salePrice,
            'colors' => $colors,
            'description_raw' => $metaText,
            'thumb' => $colors[0]['primary'] ?? '',
        ];
    }

    return $products;
}

if ($slug === 'saias') {
    $products = build_saias_products($mediaBaseDir);
    $category['products'] = $products;
} else {
    $assetProducts = load_asset_products($slug, $products, $pricingOverrides);
    if (!empty($assetProducts)) {
        $products = $assetProducts;
    }
}

$otherCategories = array_filter(
    $catalog,
    fn($key) => $key !== $slug,
    ARRAY_FILTER_USE_KEY
);

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($category['name']) ?> | Catálogo Vésteme</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="icon" type="image/jpeg" href="favicon.ico">
</head>

<body class="category-body">
    <header class="category-top">
        <a class="back-link" href="index.php" aria-label="Voltar para a campanha">
            <span>&larr;</span> Voltar para o portal
        </a>
        <div class="category-chip"><?= htmlspecialchars($category['name']) ?></div>
        <div class="countdown ticker">
            <div class="brick">
                <span class="value" data-countdown="days">--</span>
                <span class="label">Dias</span>
            </div>
            <div class="brick">
                <span class="value" data-countdown="hours">--</span>
                <span class="label">Horas</span>
            </div>
            <div class="brick">
                <span class="value" data-countdown="minutes">--</span>
                <span class="label">Min</span>
            </div>
            <div class="brick">
                <span class="value" data-countdown="seconds">--</span>
                <span class="label">Seg</span>
            </div>
        </div>
        <button id="cartToggle" class="cart-toggle" data-cart-toggle>
            🛒
            <span id="cartBadge" class="cart-badge">0</span>
        </button>

    </header>

    <main class="category-shell">
        <section class="catalog-section" id="produtos">
            <div class="product-grid product-grid-neo">
                <?php foreach ($products as $product): ?>
                    <?php
                    $colors = $product['colors'] ?? [];
                    if (empty($colors)) {
                        $fallbackGallery = $product['gallery'] ?? ($product['thumb'] ? [$product['thumb']] : []);
                        $colors = [[
                            'label' => 'Única',
                            'slug' => 'default',
                            'primary' => $fallbackGallery[0] ?? ($product['thumb'] ?? ''),
                            'gallery' => $fallbackGallery,
                            'videos' => [],
                        ]];
                    }
                    $firstColor = $colors[0] ?? null;
                    $colorMediaMap = [];
                    foreach ($colors as $color) {
                        $colorMediaMap[$color['slug']] = [
                            'label' => $color['label'],
                            'primary' => $color['primary'],
                            'gallery' => $color['gallery'],
                            'videos' => $color['videos'],
                        ];
                    }
                    $colorsJson = htmlspecialchars(json_encode($colorMediaMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                    $initialColorSlug = $firstColor['slug'] ?? '';
                    $initialPrimary = $firstColor['primary'] ?? '';
                    $initialGallery = $firstColor['gallery'] ?? [];
                    $initialVideos = $firstColor['videos'] ?? [];
                    $sizesLine = $product['sizes_line'] ?? (!empty($product['sizes']) ? implode(' ', (array)$product['sizes']) : '');
                    $sizeOptions = $product['sizes_parsed'] ?? [];
                    if (empty($sizeOptions)) {
                        $fallbackTokens = array_values(array_unique(array_filter(preg_split('/\s+/', preg_replace('/[^A-Za-z0-9\s]/', ' ', $sizesLine ?? '')), 'strlen')));
                        if (empty($fallbackTokens)) {
                            $fallbackTokens = ['--'];
                        }
                        $sizeOptions = array_map(static fn($token) => ['value' => $token, 'label' => $token], $fallbackTokens);
                    }
                    $payments = $product['payments'] ?? [];
                    $salePrice = $product['sale_price'] ?? 0;
                    $originalPrice = $product['original_price'] ?? $salePrice;
                    $economy = max(0, ($originalPrice ?? 0) - ($salePrice ?? 0));
                    ?>
                    <article class="product-card neo-layout" data-product-card data-product-id="<?= htmlspecialchars($product['id']) ?>" data-active-color="<?= htmlspecialchars($initialColorSlug) ?>">
                        <script type="application/json" class="color-media-data">
                            <?= $colorsJson ?>
                        </script>

                        <div class="media-column">
                            <div class="thumbs-rail" data-thumbs>
                                <?php if ($initialPrimary): ?>
                                    <button type="button" class="media-thumb is-active" data-gallery-thumb data-media-type="image" data-src="<?= htmlspecialchars($initialPrimary) ?>">
                                        <img src="<?= htmlspecialchars($initialPrimary) ?>" alt="miniatura <?= htmlspecialchars($product['name']) ?>">
                                    </button>
                                <?php endif; ?>
                                <?php foreach ($initialGallery as $image): ?>
                                    <button type="button" class="media-thumb" data-gallery-thumb data-media-type="image" data-src="<?= htmlspecialchars($image) ?>">
                                        <img src="<?= htmlspecialchars($image) ?>" alt="miniatura <?= htmlspecialchars($product['name']) ?>">
                                    </button>
                                <?php endforeach; ?>
                                <?php foreach ($initialVideos as $video): ?>
                                    <button type="button" class="media-thumb media-thumb-video" data-gallery-thumb data-media-type="video" data-src="<?= htmlspecialchars($video) ?>">
                                        <span class="video-icon">▶</span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <div class="media-viewer" data-gallery-main data-media-type="image">
                                <?php if ($initialPrimary): ?>
                                    <img src="<?= htmlspecialchars($initialPrimary) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="product-panel">
                            <div class="panel-head">
                                <span class="badge status-badge">Novo</span>
                                <div class="panel-title">
                                    <p class="product-tag"><?= htmlspecialchars($category['name']) ?></p>
                                    <h3><?= htmlspecialchars($product['name']) ?></h3>
                                </div>
                            </div>

                            <div class="price-row">
                                <div>
                                    <?php if ($originalPrice && $originalPrice > $salePrice): ?>
                                        <p class="price-before">R$ <?= number_format($originalPrice, 2, ',', '.') ?></p>
                                    <?php endif; ?>
                                    <p class="price-now">R$ <?= number_format($salePrice, 2, ',', '.') ?></p>
                                    <?php if (!empty($payments[0]['label'])): ?>
                                        <p class="payment-hint"><?= htmlspecialchars($payments[0]['label']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <span class="saving-pill">Economize <?= number_format($economy, 2, ',', '.') ?> por peca</span>
                            </div>

                            <div class="selectors neo stacked">
                                <div class="selector">
                                    <label>Cor</label>
                                    <div class="pill-group" data-color-pills>
                                        <?php foreach ($colors as $color): ?>
                                            <button type="button" class="pill <?= $color['slug'] === $initialColorSlug ? 'is-active' : '' ?>" data-color-option data-color="<?= htmlspecialchars($color['slug']) ?>">
                                                <?= htmlspecialchars($color['label']) ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <select name="color" data-color hidden>
                                        <option value="">Selecione</option>
                                        <?php foreach ($colors as $color): ?>
                                            <option value="<?= htmlspecialchars($color['slug']) ?>" <?= $color['slug'] === $initialColorSlug ? 'selected' : '' ?>><?= htmlspecialchars($color['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="selector">
                                    <label>Tamanho</label>
                                    <div class="pill-group" data-size-pills>
                                        <?php foreach ($sizeOptions as $opt): ?>
                                            <button type="button" class="pill" data-size-option data-size="<?= htmlspecialchars($opt['label']) ?>"><?= htmlspecialchars($opt['label']) ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <select name="size" data-size hidden>
                                        <option value="">Selecione</option>
                                        <?php foreach ($sizeOptions as $opt): ?>
                                            <option value="<?= htmlspecialchars($opt['label']) ?>"><?= htmlspecialchars($opt['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="selector quantity-compact">
                                    <label>Quantidade</label>
                                    <input type="number" name="quantity" min="1" value="1">
                                </div>
                            </div>

                            <div class="product-meta-block">
                                <?php if (!empty($product['fabric'])): ?>
                                    <p><strong>Tecido:</strong> <?= htmlspecialchars($product['fabric']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($product['info'])): ?>
                                    <div class="meta-list">
                                        <strong>Informações:</strong>
                                        <ul>
                                            <?php foreach ($product['info'] as $info): ?>
                                                <?php foreach (array_filter(array_map('trim', explode('|', $info))) as $piece): ?>
                                                    <li><?= htmlspecialchars($piece) ?></li>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($product['measures'])): ?>
                                    <div class="meta-list">
                                        <strong>Medidas:</strong>
                                        <ul>
                                            <?php foreach ($product['measures'] as $measure): ?>
                                                <?php foreach (array_filter(array_map('trim', explode('|', $measure))) as $piece): ?>
                                                    <li><?= htmlspecialchars($piece) ?></li>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($payments)): ?>
                                    <div class="meta-list">
                                        <strong>Formas de pagamento:</strong>
                                        <ul>
                                            <?php foreach ($payments as $pay): ?>
                                                <?php $label = $pay['label'] ?? ''; ?>
                                                <?php foreach (array_filter(array_map('trim', explode('|', $label))) as $piece): ?>
                                                    <li><?= htmlspecialchars($piece) ?></li>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="actions-row">
                                <button type="button" class="add-btn primary" data-add-to-cart>Adicionar ao carrinho</button>
                                <button type="button" class="add-btn ghost" data-open-cart>Fechar carrinho</button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if (!empty($otherCategories)): ?>
            <section class="order-section category-navigation">
                <header>
                    <p class="section-kicker">Continuar navegando</p>
                    <h2>Explore outras categorias</h2>
                    <p>Volte apenas se quiser: basta escolher outra seção abaixo e seguir montando o seu carrinho.</p>
                </header>
                <div class="category-showcase">
                    <?php foreach ($otherCategories as $otherSlug => $otherCategory): ?>
                        <?php if (!isset($categoryIllustrations[$otherSlug])) continue; ?>
                        <article class="category-card">
                            <div class="icon-ring">
                                <img src="assets/img/<?= htmlspecialchars($categoryIllustrations[$otherSlug]) ?>" alt="Ilustracao da categoria <?= htmlspecialchars($otherCategory['name']) ?>">
                            </div>
                            <strong><?= htmlspecialchars($otherCategory['name']) ?></strong>
                            <a class="mini-cta" href="category.php?slug=<?= urlencode($otherSlug) ?>">Ver mais</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <div class="cart-sidebar" id="cartSidebar" aria-hidden="true">
        <div class="cart-sidebar__overlay" id="cartSidebarOverlay"></div>
        <aside class="cart-sidebar__drawer" id="cartDrawer">
            <header class="cart-sidebar__head">
                <div>
                    <p class="section-kicker">Carrinho VIP</p>
                    <h3>Resumo</h3>
                </div>
                <button class="cart-sidebar__close" id="closeCartSidebar" aria-label="Fechar resumo">×</button>
            </header>

            <div class="cart-sidebar__metrics">
                <div>
                    <span>Itens</span>
                    <strong id="summaryItemsSecondary">0</strong>
                    <span id="summaryItems" class="sr-only">0</span>
                </div>
                <div>
                    <span>Investimento</span>
                    <strong id="cartInvest">R$ 0,00</strong>
                    <span id="summaryValue" class="sr-only">R$ 0,00</span>
                </div>
                <div>
                    <span>Economia estimada</span>
                    <strong id="cartSavingsValue">R$ 0,00</strong>
                    <span id="summarySavings" class="sr-only">R$ 0,00</span>
                </div>
            </div>

            <div class="cart-items"></div>

            <div class="cart-sidebar__actions">
                <button id="triggerCheckout" class="primary-action">Fechar carrinho e reservar acesso</button>
            </div>
        </aside>
    </div>

    <div class="checkout-overlay" id="checkoutOverlay">
        <div class="checkout-panel">
            <h2>Confirme seus dados</h2>
            <div class="checkout-summary">
                <p>Produtos selecionados</p>
                <ul id="checkoutList"></ul>
                <p id="checkoutTotals"></p>
            </div>
            <form id="checkoutForm" method="POST" action="submit.php">
                <div class="form-grid">
                    <label>Nome completo
                        <input type="text" name="full_name" required placeholder="Digite seu nome">
                    </label>
                    <label>Melhor e-mail
                        <input type="email" name="email" required placeholder="contato@seudominio.com">
                    </label>
                    <label>WhatsApp com DDD
                        <input type="tel" name="whatsapp" required placeholder="(00) 00000-0000">
                    </label>
                </div>
                <p>Confirmando você autoriza contato da Vésteme Modas sobre Black Friday, ofertas e logística.</p>
                <input type="hidden" name="cart_payload" id="cartPayload">
                <input type="hidden" name="merge_previous" id="mergePrevious" value="no">
                <div class="checkout-actions">
                    <button type="button" class="ghost" id="closeCheckout">Editar carrinho</button>
                    <input type="submit" value="Confirmar interesse" class="solid">
                </div>
            </form>
        </div>
    </div>

    <div class="merge-overlay" id="mergeOverlay" aria-hidden="true">
        <div class="merge-card">
            <button class="merge-close" type="button" id="closeMergeModal" aria-label="Fechar aviso">&times;</button>
            <p class="merge-kicker">Pedido encontrado</p>
            <h3>Você já tem um pedido ativo para <span id="mergeEmail"></span></h3>
            <p id="mergeInfo"></p>
            <div class="merge-actions">
                <button type="button" class="merge-option primary" id="mergeAppend">Somar itens ao pedido existente</button>
                <button type="button" class="merge-option secondary" id="mergeReplace">Substituir pelo novo pedido</button>
            </div>
        </div>
    </div>

    <footer>
        Vésteme Modas &mdash; Experiência exclusiva Black Friday com acesso controlado.
    </footer>
    <script>
        window.catalogProducts = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        window.catalogById = {};
        if (Array.isArray(window.catalogProducts)) {
            window.catalogProducts.forEach(function(p) {
                if (p && p.id) {
                    window.catalogById[p.id] = p;
                }
            });
        }
    </script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/catalog.js"></script>