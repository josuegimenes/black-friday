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

// Flash simples para evitar reenvio de formulário ao atualizar a página
$flash = $_SESSION['order_flash'] ?? null;
if ($flash) {
    unset($_SESSION['order_flash']);
}
$adminUser = $_SESSION['admin_user'] ?? null;

$statuses = [
    'Novo',
    'Em preparação interna',
    'Aguardando pagamento',
    'Pagamento efetuado',
    'Cancelado',
    'Em trânsito',
];

function status_chip_class(string $status): string
{
    $map = [
        'Novo' => 'status-new',
        'Em preparação interna' => 'status-prep',
        'Aguardando pagamento' => 'status-await',
        'Pagamento efetuado' => 'status-paid',
        'Cancelado' => 'status-cancel',
        'Em trânsito' => 'status-transit',
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

function currency_format($salePrice, $quantity): string
{
    $value = (float)$salePrice * (int)max(1, $quantity);
    return money($value);
}

function recalc_totals(array $items): array
{
    $totalItems = 0;
    $totalValue = 0.0;
    $totalSavings = 0.0;

    foreach ($items as $it) {
        $qty = (int)max(0, $it['quantity'] ?? 0);
        $sale = (float)($it['salePrice'] ?? 0);
        $orig = isset($it['originalPrice']) ? (float)$it['originalPrice'] : $sale;
        $totalItems += $qty;
        $totalValue += $sale * $qty;
        $totalSavings += max(0, $orig - $sale) * $qty;
    }

    return [
        'totalItems' => $totalItems,
        'totalValue' => $totalValue,
        'totalSavings' => $totalSavings,
    ];
}

function persist_items(PDO $pdo, int $leadId, array $items, array $totals): bool
{
    // Atualiza snapshot JSON
    $payload = json_encode(['items' => array_values($items), 'totals' => $totals], JSON_UNESCAPED_UNICODE);

    // Atualiza tabela principal
    $upd = $pdo->prepare('UPDATE bf_leads SET products = :p, total_items = :ti, total_value = :tv, total_savings = :ts WHERE id = :id');
    $okLead = $upd->execute([
        ':p' => $payload,
        ':ti' => $totals['totalItems'],
        ':tv' => $totals['totalValue'],
        ':ts' => $totals['totalSavings'],
        ':id' => $leadId,
    ]);

    // Regrava tabela bf_lead_items para manter consistência
    $pdo->prepare('DELETE FROM bf_lead_items WHERE lead_id = :id')->execute([':id' => $leadId]);
    $ins = $pdo->prepare('INSERT INTO bf_lead_items (lead_id, product_id, name, color, size, quantity, sale_price, original_price, image, admin_note) VALUES (:lead_id, :product_id, :name, :color, :size, :qty, :sale, :orig, :img, :note)');
    foreach ($items as $it) {
        $ins->execute([
            ':lead_id' => $leadId,
            ':product_id' => $it['productId'] ?? null,
            ':name' => $it['name'] ?? 'Produto',
            ':color' => $it['color'] ?? '',
            ':size' => $it['size'] ?? '',
            ':qty' => (int)($it['quantity'] ?? 0),
            ':sale' => (float)($it['salePrice'] ?? 0),
            ':orig' => isset($it['originalPrice']) ? (float)$it['originalPrice'] : null,
            ':img' => $it['thumb'] ?? null,
            ':note' => $it['adminNote'] ?? null,
        ]);
    }

    return $okLead;
}

function log_lead_action(PDO $pdo, int $leadId, string $action, ?int $itemIndex, string $note, array $before = null, array $after = null, ?string $adminUser = null): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO bf_leads_log (lead_id, action, item_index, note, before_snapshot, after_snapshot, admin_user)
            VALUES (:lead_id, :action, :item_index, :note, :before_snapshot, :after_snapshot, :admin_user)
        ");
        $stmt->execute([
            ':lead_id' => $leadId,
            ':action' => $action,
            ':item_index' => $itemIndex,
            ':note' => $note !== '' ? $note : null,
            ':before_snapshot' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            ':after_snapshot' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            ':admin_user' => $adminUser,
        ]);
    } catch (Throwable $e) {
        // Falha em log não interrompe o fluxo principal
    }
}

function fetch_items_from_db(PDO $pdo, int $leadId): array
{
    $stmt = $pdo->prepare('SELECT * FROM bf_lead_items WHERE lead_id = :id ORDER BY id ASC');
    $stmt->execute([':id' => $leadId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) return [];
    return array_map(static function ($row) {
        return [
            'productId' => $row['product_id'] ?? null,
            'name' => $row['name'] ?? 'Produto',
            'color' => $row['color'] ?? '',
            'size' => $row['size'] ?? '',
            'quantity' => (int)($row['quantity'] ?? 0),
            'salePrice' => (float)($row['sale_price'] ?? 0),
            'originalPrice' => isset($row['original_price']) ? (float)$row['original_price'] : null,
            'thumb' => $row['image'] ?? null,
            'adminNote' => $row['admin_note'] ?? null,
        ];
    }, $rows);
}

function product_image_url(array $item): ?string
{
    $candidates = ['image','img','photo','picture','thumb','thumbnail','imageUrl','imageURL','image_url','thumb_url','thumbnail_url'];
    foreach ($candidates as $k) {
        if (!empty($item[$k]) && is_string($item[$k])) return $item[$k];
    }
    return null;
}

function format_brasilia_datetime(?string $value, string $format = 'd/m/Y H:i'): string
{
    if (!$value) return '-';
    try {
        $date = new DateTime($value);
        $date->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        return $date->format($format);
    } catch (Throwable $e) {
        return '-';
    }
}

function build_catalog_from_mock(): array
{
    $path = __DIR__ . '/../data/catalog_products.php';
    if (!is_file($path)) return [];
    $data = require $path;
    if (!is_array($data)) return [];
    $catalog = [];
    foreach ($data as $prod) {
        $id = strtolower(trim($prod['id'] ?? ($prod['name'] ?? '')));
        $name = trim($prod['name'] ?? ($prod['id'] ?? 'Produto'));
        if ($id === '') continue;
        $catalog[$id] = [
            'id' => $id,
            'name' => $name,
            'colors' => array_values(array_unique($prod['colors'] ?? [])),
            'sizes' => array_values(array_unique($prod['sizes'] ?? [])),
        ];
    }
    return $catalog;
}

$catalogProducts = build_catalog_from_mock();
// Helper para ajustar thumb ao trocar produto/cor
function adjust_thumb(?string $thumb, ?string $productId, ?string $color): ?string
{
    if (!$thumb) return $thumb;

    $parsed = parse_url($thumb);
    $prefix = '';
    if (!empty($parsed['scheme']) && !empty($parsed['host'])) {
        $prefix = $parsed['scheme'] . '://' . $parsed['host'];
    }

    $productDir = trim(str_replace(['-', '%20'], ' ', strtolower($productId ?? '')));
    $colorDir = trim(str_replace(['-', '%20'], ' ', strtolower($color ?? '')));
    if ($productDir === '' || $colorDir === '') return $thumb;

    $filename = pathinfo($parsed['path'] ?? 'principal.png', PATHINFO_FILENAME);
    if ($filename === '') $filename = 'principal';

    $candidates = ['png', 'jpg', 'jpeg', 'webp'];
    $baseFs = realpath(__DIR__ . '/../assets/img/saias');
    if ($baseFs === false) return $thumb;

    foreach ($candidates as $ext) {
        $testRel = '/saias/' . rawurlencode($productDir) . '/' . rawurlencode($colorDir) . '/' . $filename . '.' . $ext;
        $fsPath = $baseFs . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $productDir . '/' . $colorDir . '/' . $filename . '.' . $ext);
        $fsPath = rawurldecode($fsPath);
        if (is_file($fsPath)) {
            return $prefix . '/assets/img' . $testRel;
        }
    }

    return $thumb;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM bf_leads WHERE id = :id');
$stmt->execute([':id' => $id]);
$lead = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$lead) {
    header('Location: index.php');
    exit;
}

$message = $flash['msg'] ?? null;
$messageType = $flash['type'] ?? 'success';

$productsPayload = json_decode($lead['products'] ?? '', true) ?: [];
$items  = is_array($productsPayload['items'] ?? null) ? $productsPayload['items'] : [];
$totals = is_array($productsPayload['totals'] ?? null) ? $productsPayload['totals'] : [];
$dbItems = fetch_items_from_db($pdo, $id);
// Prioriza itens persistidos no banco para garantir consistência visual no admin
if (!empty($dbItems)) {
    $items = $dbItems;
    $totals = recalc_totals($items);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_status';

    if ($action === 'update_status') {
        $newStatus = trim($_POST['status'] ?? '');
        if ($newStatus === '' || !in_array($newStatus, $statuses, true)) {
            $messageType = 'error';
            $message = 'Status inválido.';
        } else {
            $up = $pdo->prepare('UPDATE bf_leads SET status = :s WHERE id = :id');
            if ($up->execute([':s' => $newStatus, ':id' => $id])) {
                $messageType = 'success';
                $message = 'Status atualizado com sucesso.';
            } else {
                $messageType = 'error';
                $message = 'Falha ao atualizar o status.';
            }
        }
    } elseif ($action === 'remove_item') {
        $idx = (int)($_POST['item_index'] ?? -1);
        $note = trim($_POST['note'] ?? '');
        if (!isset($items[$idx])) {
            $messageType = 'error';
            $message = 'Item n�o encontrado para remo��o.';
        } else {
            $before = $items;
            array_splice($items, $idx, 1);
            $totals = recalc_totals($items);
            $ok = persist_items($pdo, $id, $items, $totals);
            if ($ok) {
                $messageType = 'success';
                $message = 'Item removido e totais atualizados.';
                log_lead_action($pdo, $id, 'remove_item', $idx, $note, $before, $items, $adminUser);
            } else {
                $messageType = 'error';
                $message = 'N�o foi poss�vel remover o item.';
            }
        }
    } elseif ($action === 'edit_item') {
        $idx = (int)($_POST['item_index'] ?? -1);
        if (!isset($items[$idx])) {
            $messageType = 'error';
            $message = 'Item n�o encontrado para edi��o.';
        } else {
            $before = $items;
            $pid = strtolower(trim($_POST['product_id'] ?? ''));
            $color = trim($_POST['color'] ?? '');
            $size = trim($_POST['size'] ?? '');
            $qty = max(1, (int)($_POST['quantity'] ?? 1));
            $sale = max(0, (float)($_POST['sale_price'] ?? 0));
            $orig = ($_POST['original_price'] ?? '') !== '' ? (float)$_POST['original_price'] : null;
            $note = trim($_POST['note'] ?? '');

            $catalogItem = $catalogProducts[$pid] ?? null;
            $name = $catalogItem['name'] ?? ($items[$idx]['name'] ?? 'Produto');

            // Impede duplicar combina��es id+cor+tamanho em outro item
            $norm = static function($val) { return strtolower(trim((string)$val)); };
            $newCombo = $norm($pid) . '|' . $norm($color) . '|' . $norm($size);
            foreach ($items as $j => $ex) {
                if ($j === $idx) continue;
                $combo = $norm($ex['productId'] ?? '') . '|' . $norm($ex['color'] ?? '') . '|' . $norm($ex['size'] ?? '');
                if ($combo === $newCombo) {
                    $_SESSION['order_flash'] = [
                        'type' => 'error',
                        'msg' => 'Esta combinaçãoo de produto/cor/tamanho já existe em outro item. Edite o item correspondente ou remova este antes de prosseguir.'
                    ];
                    header('Location: order.php?id=' . $id);
                    exit;
                }
            }

            $items[$idx]['productId'] = $pid ?: ($items[$idx]['productId'] ?? null);
            $items[$idx]['name'] = $name;
            $items[$idx]['color'] = $color ?: ($items[$idx]['color'] ?? '');
            $items[$idx]['size'] = $size ?: ($items[$idx]['size'] ?? '');
            $items[$idx]['quantity'] = $qty;
            $items[$idx]['salePrice'] = $sale;
            if ($orig !== null) {
                $items[$idx]['originalPrice'] = $orig;
            }
            // Ajusta a imagem se o produto/cor mudar
            $items[$idx]['thumb'] = adjust_thumb($items[$idx]['thumb'] ?? ($items[$idx]['image'] ?? null), $pid, $color);
            if ($note !== '') {
                $items[$idx]['adminNote'] = $note;
            }

            $totals = recalc_totals($items);
            $ok = persist_items($pdo, $id, $items, $totals);
            if ($ok) {
                $messageType = 'success';
                $message = 'Item atualizado.';
                log_lead_action($pdo, $id, 'edit_item', $idx, $note, $before, $items, $adminUser);
            } else {
                $messageType = 'error';
                $message = 'N�o foi poss�vel atualizar o item.';
            }
        }
    }

    $_SESSION['order_flash'] = ['type' => $messageType, 'msg' => $message];
    header('Location: order.php?id=' . $id);
    exit;
}

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

            <div class="card pad">
                <div class="order-head">
                    <div class="cell">
                        <span class="cell-label">Cliente</span>
                        <strong><?= safe($lead['full_name'] ?? '-') ?></strong>
                    </div>
                    <div class="cell">
                        <span class="cell-label">E-mail</span>
                        <strong><?= safe($lead['email'] ?? '-') ?></strong>
                    </div>
                    <div class="cell">
                        <span class="cell-label">WhatsApp</span>
                        <strong><?= safe($lead['whatsapp'] ?? '-') ?></strong>
                    </div>
                    <div class="cell">
                        <span class="cell-label">Entrada</span>
                        <strong><?= format_brasilia_datetime($lead['created_at'] ?? null) ?></strong>
                    </div>
                </div>

                <div class="kpis">
                    <div class="kpi"><span>Itens selecionados</span><strong><?= (int)$totalItems ?></strong></div>
                    <div class="kpi"><span>Investimento</span><strong><?= money($totalValue) ?></strong></div>
                    <div class="kpi kpi--success"><span>Desconto previsto</span><strong><?= money($totalSavings) ?></strong></div>
                    <div class="kpi kpi--status">
                        <span>Status atual</span>
                        <div class="kpi-status-chip">
                            <span class="status-chip <?= status_chip_class($lead['status'] ?? '') ?>">
                                <?= safe($lead['status'] ?? 'Em preparação interna') ?>
                            </span>
                        </div>
                    </div>

                    <div class="status-box">
                        <div class="status-row">
                            <form method="POST" class="status-form" onsubmit="this.querySelector('button[type=submit]').disabled=true;">
                                <input type="hidden" name="action" value="update_status">
                                <label class="status-label">
                                    Atualizar status
                                    <div class="select-wrapper">
                                        <select name="status">
                                            <?php foreach ($statuses as $status): ?>
                                                <option value="<?= safe($status) ?>" <?= (($lead['status'] ?? '') === $status) ? 'selected' : '' ?>><?= safe($status) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </label>
                                <button type="submit" class="btn-primary">Salvar status</button>
                            </form>
                            <div class="inline-actions inline-actions-tight">
                                <a class="btn-ghost" href="mailto:<?= safe($lead['email']) ?>">Contatar por e-mail</a>
                                <a class="btn-ghost" target="_blank" href="https://wa.me/55<?= preg_replace('/\D+/', '', $lead['whatsapp'] ?? '') ?>?text=Olá%2C%20<?= rawurlencode($lead['full_name'] ?? '') ?>!">Abrir WhatsApp</a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <p class="alert order-alert <?= $messageType === 'error' ? 'alert-error' : 'alert-success' ?>"><?= safe($message) ?></p>
                <?php endif; ?>
            </div>

            <div class="card pad">
                <h2 class="section-title">Resumo do carrinho</h2>

                <?php if (!$items): ?>
                    <div class="empty-state">Nenhum item registrado.</div>
                <?php else: ?>
                    <div class="items-list">
                        <?php foreach ($items as $idx => $it):
                            $name  = trim((string)($it['name'] ?? 'Produto'));
                            $size  = trim((string)($it['size'] ?? '-'));
                            $color = trim((string)($it['color'] ?? '-'));
                            $qty   = (int)($it['quantity'] ?? 0);
                            $sale  = (float)($it['salePrice'] ?? 0.0);
                            $orig  = isset($it['originalPrice']) ? (float)$it['originalPrice'] : null;
                            $rawImg = product_image_url($it);
                            $img   = $rawImg ?: 'https://via.placeholder.com/120x160?text=Foto';
                        ?>
                            <div class="item">
                                <div class="thumb">
                                    <img src="<?= safe($img) ?>" alt="<?= safe($name) ?>">
                                </div>
                                <div>
                                    <div class="title"><?= safe(mb_strtoupper($name, 'UTF-8')) ?></div>
                                    <div class="meta">
                                        <div class="row"><span>Tamanho: <?= safe($size) ?></span></div>
                                        <div class="row"><span>Cor: <?= safe($color) ?></span></div>
                                        <div class="row"><span>Quantidade: <?= $qty ?> un</span></div>
                                        <div class="row"><span>Preço original: <?= money($orig !== null ? $orig : $sale) ?></span></div>
                                        <div class="row"><span>Preço unitário: <?= money($sale) ?></span></div>
                                    </div>
                                </div>
                                <div class="right">
                                    <div class="muted">Investimento</div>
                                    <div class="price"><?= currency_format($sale, $qty) ?></div>
                                </div>
                                <div class="item-actions-bar">
                                    <button type="button" class="icon-btn" data-open-modal="edit-<?= $idx ?>" title="Editar item">✎</button>
                                    <button type="button" class="icon-btn danger" data-open-modal="remove-<?= $idx ?>" title="Remover item">🗑</button>
                                </div>
                            </div>

                            <div class="modal-backdrop is-hidden" data-modal="remove-<?= $idx ?>">
                                <div class="modal-card">
                                    <button type="button" class="modal-close" data-close-modal>×</button>
                                    <h3>Remover item</h3>
                                    <p class="muted">Selecione o motivo e confirme.</p>
                                    <form method="POST" class="modal-form" onsubmit="this.querySelector('button[type=submit]').disabled=true;">
                                        <input type="hidden" name="action" value="remove_item">
                                        <input type="hidden" name="item_index" value="<?= $idx ?>">
                                        <label>Motivo/observação
                                            <select name="note" required>
                                                <option value="">Selecione</option>
                                                <option value="Produto esgotado">Produto esgotado</option>
                                                <option value="Cor esgotada">Cor esgotada</option>
                                                <option value="Tamanho esgotado">Tamanho esgotado</option>
                                                <option value="Quantidade insuficiente">Quantidade insuficiente</option>
                                                <option value="Trocar por outro item">Trocar por outro item</option>
                                                <option value="Cancelado pelo cliente">Cancelado pelo cliente</option>
                                            </select>
                                        </label>
                                        <div class="modal-actions">
                                            <button type="button" class="btn-ghost" data-close-modal>Cancelar</button>
                                            <button type="submit" class="btn-danger">Remover</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="modal-backdrop is-hidden" data-modal="edit-<?= $idx ?>">
                                <div class="modal-card">
                                    <button type="button" class="modal-close" data-close-modal>×</button>
                                    <h3>Editar item</h3>
                                    <p class="muted">Atualize os dados abaixo.</p>
                                    <form method="POST" class="modal-form" onsubmit="this.querySelector('button[type=submit]').disabled=true;">
                                        <input type="hidden" name="action" value="edit_item">
                                        <input type="hidden" name="item_index" value="<?= $idx ?>">
                                        <div class="grid-edit">
                                            <label class="full-row">Produto
                                                <select name="product_id" data-product-select data-item="<?= $idx ?>">
                                                    <?php
                                                    $currentPid = strtolower(trim($it['productId'] ?? ''));
                                                    if ($currentPid === '' && $name !== '') {
                                                        foreach ($catalogProducts as $pid => $p) {
                                                            if (strtolower($p['name']) === strtolower($name)) {
                                                                $currentPid = $pid;
                                                                break;
                                                            }
                                                        }
                                                    }
                                                    foreach ($catalogProducts as $pid => $p):
                                                        $selected = ($pid === $currentPid);
                                                        ?>
                                                        <option value="<?= safe($pid) ?>" <?= $selected ? 'selected' : '' ?>><?= safe($p['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <label>Cor
                                                <select name="color" data-color-select data-item="<?= $idx ?>" data-current="<?= safe($color) ?>">
                                                    <option value="<?= safe($color) ?>" selected><?= safe($color) ?></option>
                                                </select>
                                            </label>
                                            <label>Tamanho
                                                <select name="size" data-size-select data-item="<?= $idx ?>" data-current="<?= safe($size) ?>">
                                                    <option value="<?= safe($size) ?>" selected><?= safe($size) ?></option>
                                                </select>
                                            </label>
                                            <label class="is-qty">Qtd
                                                <input type="number" name="quantity" min="1" inputmode="numeric" value="<?= $qty ?>">
                                            </label>
                                            <label>Preço promocional
                                                <input type="text" name="sale_price" inputmode="decimal" value="<?= number_format((float)$sale, 2, ',', '.') ?>" data-money>
                                            </label>
                                            <label>Preço original
                                                <input type="text" name="original_price" inputmode="decimal" value="<?= $orig !== null ? number_format((float)$orig, 2, ',', '.') : '' ?>" data-money>
                                            </label>
                                        </div>
                                        <label>Obs. para o cliente
                                            <input type="text" name="note" maxlength="180" value="<?= safe($it['adminNote'] ?? '') ?>">
                                        </label>
                                        <div class="modal-actions">
                                            <button type="button" class="btn-ghost" data-close-modal>Cancelar</button>
                                            <button type="submit" class="btn-primary">Salvar alterações</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</body>

<script>
(function() {
    const catalog = <?= json_encode(array_values($catalogProducts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const openButtons = document.querySelectorAll('[data-open-modal]');
    const closeButtons = document.querySelectorAll('[data-close-modal]');
    const backdrops = document.querySelectorAll('.modal-backdrop');

    function openModal(id) {
        const modal = document.querySelector(`[data-modal="${id}"]`);
        if (modal) modal.classList.remove('is-hidden');
    }
    function closeModal(el) {
        const backdrop = el.closest('.modal-backdrop');
        if (backdrop) backdrop.classList.add('is-hidden');
    }

    openButtons.forEach(btn => btn.addEventListener('click', () => openModal(btn.getAttribute('data-open-modal'))));
    closeButtons.forEach(btn => btn.addEventListener('click', () => closeModal(btn)));
    backdrops.forEach(b => b.addEventListener('click', (e) => { if (e.target === b) b.classList.add('is-hidden'); }));

    function populate(productId, colorSelect, sizeSelect, currentColor, currentSize) {
        const item = catalog.find(p => (p.id || '').toLowerCase() === (productId || '').toLowerCase());
        const colors = item && Array.isArray(item.colors) ? item.colors : [];
        const sizes = item && Array.isArray(item.sizes) ? item.sizes : [];
        if (colorSelect) {
            colorSelect.innerHTML = '';
            colors.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c;
                opt.textContent = c;
                colorSelect.appendChild(opt);
            });
            if (colors.length) colorSelect.value = colors.includes(currentColor) ? currentColor : colors[0];
        }
        if (sizeSelect) {
            sizeSelect.innerHTML = '';
            sizes.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s;
                opt.textContent = s;
                sizeSelect.appendChild(opt);
            });
            if (sizes.length) sizeSelect.value = sizes.includes(currentSize) ? currentSize : sizes[0];
        }
    }

    document.querySelectorAll('[data-product-select]').forEach(select => {
        const idx = select.getAttribute('data-item');
        const colorSelect = document.querySelector(`[data-color-select][data-item="${idx}"]`);
        const sizeSelect = document.querySelector(`[data-size-select][data-item="${idx}"]`);
        const currentColor = colorSelect ? colorSelect.getAttribute('data-current') || colorSelect.value : '';
        const currentSize = sizeSelect ? sizeSelect.getAttribute('data-current') || sizeSelect.value : '';
        populate(select.value, colorSelect, sizeSelect, currentColor, currentSize);
        select.addEventListener('change', () => populate(select.value, colorSelect, sizeSelect, '', ''));
    });

    const moneyInputs = document.querySelectorAll('[data-money]');
    const formatMoney = (el) => {
        let digits = (el.value || '').replace(/\D+/g, '');
        if (!digits) { el.value = ''; return; }
        if (digits.length === 1) digits = '0' + digits;
        const cents = digits.slice(-2);
        let intPart = digits.slice(0, -2) || '0';
        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        el.value = `${intPart},${cents}`;
    };
    moneyInputs.forEach(el => {
        if (el.value && !el.value.includes(',')) {
            const num = parseFloat(el.value.replace(/\./g, '').replace(',', '.'));
            if (!isNaN(num)) el.value = num.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        el.addEventListener('input', () => formatMoney(el));
    });

    document.querySelectorAll('.modal-form').forEach(form => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('[data-money]').forEach(inp => {
                if (!inp.value) return;
                const num = parseFloat(inp.value.replace(/\./g, '').replace(',', '.'));
                inp.value = isNaN(num) ? '' : num.toFixed(2);
            });
        });
    });
})();
</script>
</html>



