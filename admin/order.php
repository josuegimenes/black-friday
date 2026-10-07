<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (empty($_SESSION['admin_authenticated'])) {
    header('Location: login.php');
    exit;
}

require __DIR__ . '/../config/db.php';

$pdo = get_pdo();

/** Status disponíveis e helper de cor/badge */
$statuses = [
    'Em preparação interna',
    'Aguardando pagamento',
    'Pagamento efetuado',
    'Cancelado',
    'Em trânsito',
];

function status_chip_class(string $status): string
{
    $map = [
        'Em preparação interna' => 'status-prep',
        'Aguardando pagamento'  => 'status-await',
        'Pagamento efetuado'    => 'status-paid',
        'Cancelado'             => 'status-cancel',
        'Em trânsito'           => 'status-transit',
    ];
    return $map[$status] ?? 'status-prep';
}

function safe(?string $t): string
{
    return htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
}
function money($v): string
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}
function currency_format($salePrice, $quantity)
{
    $value = (float)$salePrice * (int)max(1, $quantity);
    return money($value);
}

/** Detecta possível campo de imagem no item do carrinho */
function product_image_url(array $item): ?string
{
    $candidates = [
        'image',
        'img',
        'photo',
        'picture',
        'thumb',
        'thumbnail',
        'imageUrl',
        'imageURL',
        'image_url',
        'thumb_url',
        'thumbnail_url'
    ];
    foreach ($candidates as $k) {
        if (!empty($item[$k]) && is_string($item[$k])) return $item[$k];
    }
    return null;
}

function admin_site_origin(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'vesteme.com.br';
    return $scheme . '://' . $host;
}

function normalize_thumb_url(?string $url): ?string
{
    if (!$url) {
        return null;
    }

    $trimmed = trim($url);
    if ($trimmed === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $trimmed)) {
        return $trimmed;
    }

    return admin_site_origin() . '/' . ltrim($trimmed, '/');
}

/** Placeholder minimalista que respeita a identidade */
function placeholder_image(): string
{
    $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='160' height='160'>
      <defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'>
        <stop stop-color='#0c0c12' offset='0'/>
        <stop stop-color='#14141f' offset='1'/>
      </linearGradient></defs>
      <rect fill='url(#g)' width='100%' height='100%'/>
      <circle cx='80' cy='66' r='24' fill='rgba(255,255,255,0.06)'/>
      <rect x='34' y='108' width='92' height='10' rx='5' fill='rgba(255,255,255,0.08)'/>
    </svg>";
    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

/** Carrega pedido */
$stmt = $pdo->prepare('SELECT * FROM bf_leads WHERE id = :id');
$stmt->execute([':id' => $id]);
$lead = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$lead) {
    header('Location: index.php');
    exit;
}

/** Processa POST (atualização de status) */
$message = null;
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = trim($_POST['status'] ?? '');
    if ($newStatus === '' || !in_array($newStatus, $statuses, true)) {
        $messageType = 'error';
        $message = 'Status inválido.';
    } else {
        $up = $pdo->prepare('UPDATE bf_leads SET status = :s WHERE id = :id');
        if ($up->execute([':s' => $newStatus, ':id' => $id])) {
            $messageType = 'success';
            $message = 'Status atualizado com sucesso.';
            $stmt = $pdo->prepare('SELECT * FROM bf_leads WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $lead = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $messageType = 'error';
            $message = 'Falha ao atualizar o status.';
        }
    }
}

/** Decodifica JSON de produtos/totais */
$productsPayload = json_decode($lead['products'] ?? '', true) ?: [];
$items  = is_array($productsPayload['items'] ?? null) ? $productsPayload['items'] : [];
$totals = is_array($productsPayload['totals'] ?? null) ? $productsPayload['totals'] : [];

$totalItems   = (int)($totals['totalItems'] ?? $lead['total_items'] ?? 0);
$totalValue   = (float)($totals['totalValue'] ?? $lead['total_value'] ?? 0);
$totalSavings = (float)($totals['totalSavings'] ?? $lead['total_savings'] ?? 0);

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Pedido #<?= (int)$lead['id'] ?> | Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="assets/order.css">
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
</head>

<body>

    <header class="dashboard-top">
        <div>
            <p class="badge">Detalhes do pedido</p>
            <h1>Pedido #<?= (int)$lead['id'] ?></h1>
        </div>
        <div class="nav-actions">
            <a class="btn btn-secondary" href="index.php">Voltar</a>
            <a class="btn btn-secondary" href="logout.php">Sair</a>
        </div>
    </header>

    <div class="layout">
        <div class="order-shell">

            <!-- Bloco superior: informações do cliente + KPIs + status -->
            <div class="card pad">

                <!-- Dados do cliente com novos ícones -->
                <div class="order-head">
                    <div class="cell">
                        <span class="cell-label">
                            <span class="info-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <defs>
                                        <linearGradient id="grad-user" x1="0" y1="0" x2="1" y2="1">
                                            <stop offset="0" stop-color="#ff4fde" />
                                            <stop offset="1" stop-color="#fddf4a" />
                                        </linearGradient>
                                    </defs>
                                    <path fill="url(#grad-user)"
                                        d="M12 12a4 4 0 1 0-4-4 4.003 4.003 0 0 0 4 4Zm0 2c-4.41 0-8 2.24-8 5v1h16v-1c0-2.76-3.59-5-8-5Z" />
                                </svg>
                            </span>
                            Cliente
                        </span>
                        <strong><?= safe($lead['full_name'] ?? '-') ?></strong>
                    </div>
                    <div class="cell">
                        <span class="cell-label">
                            <span class="info-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <defs>
                                        <linearGradient id="grad-mail" x1="0" y1="0" x2="1" y2="1">
                                            <stop offset="0" stop-color="#56ccf2" />
                                            <stop offset="1" stop-color="#2f80ed" />
                                        </linearGradient>
                                    </defs>
                                    <path fill="url(#grad-mail)"
                                        d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm0 2v.01L12 11l8-4.99V6H4Zm16 12V9.24l-7.37 4.6a1.5 1.5 0 0 1-1.56 0L4 9.24V18h16Z" />
                                </svg>
                            </span>
                            E-mail
                        </span>
                        <strong><?= safe($lead['email'] ?? '-') ?></strong>
                    </div>
                    <div class="cell">
                        <span class="cell-label">
                            <span class="info-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <defs>
                                        <linearGradient id="grad-whats" x1="0" y1="0" x2="1" y2="1">
                                            <stop offset="0" stop-color="#25d366" />
                                            <stop offset="1" stop-color="#2af598" />
                                        </linearGradient>
                                    </defs>
                                    <path fill="url(#grad-whats)"
                                        d="M20 4.5A9.94 9.94 0 0 0 12.02 2 10 10 0 0 0 3 12.03a9.86 9.86 0 0 0 1.37 5L3 22l5-1.31A10.17 10.17 0 0 0 12.03 22 10 10 0 0 0 20 4.5Zm-7.97 15a8.33 8.33 0 0 1-4.25-1.17L7 18.1l-2.1.55.56-2.05-.4-.63a8.33 8.33 0 0 1-1.3-4.37 8.04 8.04 0 0 1 8.25-8.05 8.1 8.1 0 0 1 8.19 8.03 8.08 8.08 0 0 1-8.16 8.02Zm4.34-5.34c-.26-.13-1.54-.76-1.78-.85s-.41-.13-.58.13-.67.85-.82 1-.3.19-.56.06a6.61 6.61 0 0 1-3.23-2.83c-.24-.41.24-.38.68-1.27a.48.48 0 0 0 0-.45c-.06-.13-.58-1.39-.79-1.9s-.42-.44-.58-.44h-.5a.96.96 0 0 0-.68.32 2.86 2.86 0 0 0-.89 2.12 4.98 4.98 0 0 0 1.03 2.64 11.37 11.37 0 0 0 4.35 3.8 14.8 14.8 0 0 0 1.47.54 3.53 3.53 0 0 0 1.62.1 2.65 2.65 0 0 0 1.75-1.24 2.16 2.16 0 0 0 .15-1.24c-.07-.06-.24-.12-.5-.25Z" />
                                </svg>
                            </span>
                            WhatsApp
                        </span>
                        <strong><?= safe($lead['whatsapp'] ?? '-') ?></strong>
                    </div>
                    <div class="cell">
                        <span class="cell-label">
                            <span class="info-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <defs>
                                        <linearGradient id="grad-clock" x1="0" y1="0" x2="1" y2="1">
                                            <stop offset="0" stop-color="#fddf4a" />
                                            <stop offset="1" stop-color="#ff8a4a" />
                                        </linearGradient>
                                    </defs>
                                    <path fill="url(#grad-clock)"
                                        d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8 8.009 8.009 0 0 1-8 8Zm1-8.59V7h-2v5a1 1 0 0 0 .29.71l3.3 3.3 1.42-1.42Z" />
                                </svg>
                            </span>
                            Entrada
                        </span>
                        <strong><?= !empty($lead['created_at']) ? date('d/m/Y H:i', strtotime($lead['created_at'])) : '-' ?></strong>
                    </div>
                </div>

                <!-- KPIs + card de Status atual + card de atualização -->
                <div class="kpis">
                    <div class="kpi">
                        <span>Itens selecionados</span>
                        <strong><?= (int)$totalItems ?></strong>
                    </div>
                    <div class="kpi">
                        <span>Investimento</span>
                        <strong><?= money($totalValue) ?></strong>
                    </div>
                    <div class="kpi kpi--success">
                        <span>Economia prevista</span>
                        <strong><?= money($totalSavings) ?></strong>
                    </div>
                    <div class="kpi kpi--status">
                        <span>Status atual</span>
                        <div class="kpi-status-chip">
                            <span class="status-chip <?= status_chip_class($lead['status'] ?? '') ?>">
                                <?= safe($lead['status'] ?? 'Em preparação interna') ?>
                            </span>
                        </div>
                    </div>

                    <!-- Card de atualização de status + contatos (agora dentro da grid) -->
                    <div class="status-box">
                        <div class="status-row">
                            <form method="POST"
                                class="status-form"
                                onsubmit="this.querySelector('button[type=submit]').disabled=true;">
                                <label class="status-label">
                                    Atualizar status
                                    <div class="select-wrapper">
                                        <select name="status">
                                            <?php foreach ($statuses as $status): ?>
                                                <option value="<?= safe($status) ?>" <?= (($lead['status'] ?? '') === $status) ? 'selected' : '' ?>>
                                                    <?= safe($status) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </label>
                                <button type="submit" class="btn-primary">Salvar status</button>
                            </form>

                            <div class="inline-actions inline-actions-tight">
                                <a class="btn-ghost" href="mailto:<?= safe($lead['email']) ?>">Contatar por e-mail</a>
                                <a class="btn-ghost" target="_blank"
                                    href="https://wa.me/55<?= preg_replace('/\D+/', '', $lead['whatsapp'] ?? '') ?>?text=Olá%2C%20<?= rawurlencode($lead['full_name'] ?? '') ?>!">
                                    Abrir WhatsApp
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <p class="alert order-alert <?= $messageType === 'error' ? 'alert-error' : 'alert-success' ?>">
                        <?= safe($message) ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Lista de itens (um por linha) -->
            <div class="card pad">
                <h2 class="section-title">Resumo do carrinho</h2>

                <?php if (!$items): ?>
                    <div class="empty-state">Nenhum item registrado.</div>
                <?php else: ?>
                    <div class="items-list">
                        <?php foreach ($items as $it):
                            $name  = trim((string)($it['name'] ?? 'Produto'));
                            $size  = trim((string)($it['size'] ?? '-'));
                            $color = trim((string)($it['color'] ?? '-'));
                            $qty   = (int)($it['quantity'] ?? 0);
                            $sale  = (float)($it['salePrice'] ?? 0.0);
                            $rawImg = product_image_url($it);
                            $img   = $rawImg ? normalize_thumb_url($rawImg) : placeholder_image();
                        ?>
                            <div class="item">
                                <div class="thumb">
                                    <img src="<?= safe($img) ?>" alt="<?= safe($name) ?>">
                                </div>

                                <div>
                                    <div class="title"><?= safe(mb_strtoupper($name, 'UTF-8')) ?></div>
                                    <div class="meta">
                                        <div class="row">
                                            <span>Tamanho: <?= safe($size) ?></span>
                                            <span>Cor: <?= safe($color) ?></span>
                                            <span>Quantidade: <?= $qty ?> un</span>
                                        </div>
                                        <div class="row">
                                            <span>Preço unitário: <?= money($sale) ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="right">
                                    <div class="muted">Investimento</div>
                                    <div class="price"><?= currency_format($sale, $qty) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</body>

</html>
