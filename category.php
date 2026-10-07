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
    $pattern = $directory . '/*.{'.implode(',', $extensions).'}';
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

$assetProducts = load_asset_products($slug, $products, $pricingOverrides);
if (!empty($assetProducts)) {
    $products = $assetProducts;
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
</header>

<main class="category-shell">
    <section class="category-hero">
        <div class="hero-copy">
            <p class="badge">Linha selecionada &bull; preco de atacado liberado</p>
            <h1><?= htmlspecialchars($category['name']) ?></h1>
            <p><?= htmlspecialchars($category['cover_copy']) ?></p>
            <div class="hero-actions">
                <a class="cta-primary" href="#produtos">Quero montar meu pedido</a>
                <span class="hero-note">Ordem de liberacao segue o cadastro confirmado.</span>
            </div>
        </div>
        <div class="category-video">
            <iframe src="<?= htmlspecialchars($category['hero_video']) ?>" title="Video explicativo" frameborder="0" allowfullscreen></iframe>
        </div>
    </section>

    <section class="catalog-section" id="produtos">
        <div class="summary-banner">
            <div>
                <strong>Simule agora</strong>
                <p>Acompanhe o potencial de economia conforme adiciona as pecas. Seu lugar na fila e garantido quando enviar os dados.</p>
            </div>
            <div class="numbers">
                <span>Pecas<b id="summaryItems">0</b></span>
                <span>Investimento<b id="summaryValue">R$ 0,00</b></span>
                <span>Economia<b id="summarySavings">R$ 0,00</b></span>
            </div>
        </div>

        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <?php
                    $gallery = !empty($product['gallery']) ? $product['gallery'] : [$product['thumb']];
                    $sizeNotes = $product['size_notes'] ?? [];
                    $quantities = $product['quantities'] ?? [];
                ?>
                <article class="product-card" data-product-card data-product-id="<?= htmlspecialchars($product['id']) ?>">
                    <div class="product-media">
                        <div class="product-thumb" data-gallery-main>
                            <img src="<?= htmlspecialchars($gallery[0]) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                        </div>
                        <?php if (count($gallery) > 1): ?>
                            <div class="product-thumbs">
                                <?php foreach ($gallery as $index => $image): ?>
                                    <button type="button" class="thumb<?= $index === 0 ? ' is-active' : '' ?>" data-gallery-thumb data-image="<?= htmlspecialchars($image) ?>">
                                        <img src="<?= htmlspecialchars($image) ?>" alt="Pre-visualizacao <?= $index + 1 ?>">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="product-content">
                        <p class="product-tag"><?= htmlspecialchars($category['name']) ?></p>
                        <h3><?= htmlspecialchars($product['name']) ?></h3>
                        <ul class="product-meta">
                            <?php if (!empty($product['fabric'])): ?>
                                <li><strong>Tecido:</strong> <?= htmlspecialchars($product['fabric']) ?></li>
                            <?php endif; ?>
                            <?php if (!empty($product['colors'])): ?>
                                <li><strong>Cores:</strong> <?= htmlspecialchars(implode(', ', $product['colors'])) ?></li>
                            <?php endif; ?>
                            <?php if (!empty($sizeNotes)): ?>
                                <li><strong>Grade:</strong>
                                    <?php foreach ($sizeNotes as $code => $note): ?>
                                        <span><?= htmlspecialchars($code) ?><?= $note ? ' (' . htmlspecialchars($note) . ')' : '' ?></span>
                                    <?php endforeach; ?>
                                </li>
                            <?php endif; ?>
                            <?php if (!empty($quantities)): ?>
                                <li><strong>Disponibilidade:</strong> <?= htmlspecialchars(implode(' | ', $quantities)) ?></li>
                            <?php endif; ?>
                        </ul>
                        <?php if (!empty($product['description'])): ?>
                            <p class="product-description"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="product-actions">
                        <div class="price-tag">
                            <span class="anchor">De <?= number_format($product['original_price'], 2, ',', '.') ?></span>
                            <span class="deal">por <?= number_format($product['sale_price'], 2, ',', '.') ?></span>
                        </div>
                        <span class="save-chip">Economize <?= number_format($product['original_price'] - $product['sale_price'], 2, ',', '.') ?> por peca</span>

                        <div class="selector">
                            <label>Tamanhos disponiveis</label>
                            <select name="size">
                                <option value="">Selecione</option>
                                <?php foreach ($product['sizes'] as $size): ?>
                                    <option value="<?= htmlspecialchars($size) ?>"><?= htmlspecialchars($size) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="selector">
                            <label>Cores</label>
                            <select name="color">
                                <option value="">Selecione</option>
                                <?php foreach ($product['colors'] as $color): ?>
                                    <option value="<?= htmlspecialchars($color) ?>"><?= htmlspecialchars($color) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="selector">
                            <label>Quantidade</label>
                            <input type="number" name="quantity" min="1" value="1">
                            <small>Replique a grade ideal para o seu estoque.</small>
                        </div>
                        <button type="button" class="add-btn" data-add-to-cart>Adicionar ao carrinho</button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="cart-panel">
            <div class="cart-head">
                <div>
                    <p class="section-kicker">Carrinho vip</p>
                    <h3>Previa do carrinho</h3>
                </div>
                <div class="cart-counter">
                    <span>Itens</span>
                    <strong id="summaryItemsSecondary">0</strong>
                </div>
            </div>
            <div class="cart-items"></div>
            <div class="cart-metrics">
                <div>
                    <span>Investimento</span>
                    <strong id="cartInvest">R$ 0,00</strong>
                </div>
                <div>
                    <span>Economia estimada</span>
                    <strong id="cartSavingsValue">R$ 0,00</strong>
                </div>
            </div>
            <button id="triggerCheckout" class="primary-action">Fechar carrinho e reservar acesso</button>
            <p class="cart-note">Liberamos os pagamentos seguindo a ordem de cadastro e estoque disponivel.</p>
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
</script>
<script src="assets/js/main.js"></script>
<script src="assets/js/catalog.js"></script>
</body>
</html>
