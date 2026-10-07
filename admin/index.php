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
$statuses = [
    'Novo',
    'Em preparação interna',
    'Aguardando pagamento',
    'Pagamento efetuado',
    'Cancelado',
    'Em trânsito',
];

/**
 * TOTAIS GERAIS (SEM FILTRO) -> para os 4 cards
 */
$globalStmt = $pdo->query("
    SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(total_items), 0)   AS total_items,
        COALESCE(SUM(total_value), 0)   AS total_value,
        COALESCE(SUM(total_savings), 0) AS total_savings
    FROM bf_leads
");
$globalTotals = $globalStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total_orders'   => 0,
    'total_items'    => 0,
    'total_value'    => 0,
    'total_savings'  => 0,
];

$globalTotalOrders  = (int) $globalTotals['total_orders'];
$globalTotalItems   = (int) $globalTotals['total_items'];
$globalTotalValue   = (float) $globalTotals['total_value'];
$globalTotalSavings = (float) $globalTotals['total_savings'];

/**
 * FILTROS (afetam apenas a lista/contagem filtrada)
 */
$query = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$orderDirection = strtolower($_GET['order'] ?? 'desc');
$orderDirection = $orderDirection === 'asc' ? 'ASC' : 'DESC';
$isFiltered = ($query !== '') || ($statusFilter !== '') || ($orderDirection !== 'DESC');

$params = [];
$clauses = [];

/** BUSCA LIVRE */
if ($query !== '') {
    $lowered = mb_strtolower($query, 'UTF-8');
    $term = '%' . $lowered . '%';

    $digitsOnly = preg_replace('/\D+/', '', $query);
    $whatsappExpression = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(whatsapp, '(', ''), ')', ''), '-', ''), ' ', ''), '+', ''), '.', '')";
    $productsExpression = "LOWER(CAST(JSON_EXTRACT(products, '$.items[*].name') AS CHAR CHARACTER SET utf8mb4))";

    $searchParts = [];

    $searchParts[] = 'LOWER(full_name) LIKE :term_full_name';
    $params[':term_full_name'] = $term;

    $searchParts[] = 'LOWER(email) LIKE :term_email';
    $params[':term_email'] = $term;

    $searchParts[] = "$productsExpression LIKE :term_products";
    $params[':term_products'] = $term;

    if ($digitsOnly !== '') {
        $params[':whatsapp_digits'] = '%' . $digitsOnly . '%';
        $searchParts[] = "$whatsappExpression LIKE :whatsapp_digits";
    } else {
        $searchParts[] = 'LOWER(whatsapp) LIKE :term_whatsapp';
        $params[':term_whatsapp'] = $term;
    }

    $clauses[] = '(' . implode(' OR ', $searchParts) . ')';
}

/** FILTRO STATUS */
if ($statusFilter !== '' && in_array($statusFilter, $statuses, true)) {
    $clauses[] = 'status = :status';
    $params[':status'] = $statusFilter;
}

$whereClause = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';

$stmt = $pdo->prepare("SELECT * FROM bf_leads $whereClause ORDER BY id $orderDirection");
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

/** TOTAIS FILTRADOS (para resumo/linha secundária dos cards sob Cancelado) */
$filteredOrders  = count($leads);
$filteredItems   = array_sum(array_column($leads, 'total_items'));
$filteredValue   = array_sum(array_column($leads, 'total_value'));
$filteredSavings = array_sum(array_column($leads, 'total_savings'));

/** Resumo */
$resultsLabel = $filteredOrders === 1 ? 'pedido encontrado' : 'pedidos encontrados';
$summaryFilters = [];
if ($query !== '') {
    $summaryFilters[] = 'busca por "<strong>' . htmlspecialchars($query, ENT_QUOTES, 'UTF-8') . '</strong>"';
}
if ($statusFilter !== '') {
    $summaryFilters[] = 'status <strong>' . htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8') . '</strong>';
}

/** Helpers */
function highlight_term(?string $text, string $query): string
{
    $text = (string)($text ?? '');
    if ($text === '' || $query === '') return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    if (preg_match('/^\d+$/', $query)) {
        $digitsOnlyText = preg_replace('/\D+/', '', $text);
        if ($digitsOnlyText === '') return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $posDigits = strpos($digitsOnlyText, $query);
        if ($posDigits === false) return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        $lenQueryDigits = strlen($query);
        $currentDigitIndex = 0;
        $startCharIndex = null; $endCharIndex = null;
        $strlenText = strlen($text);

        for ($i = 0; $i < $strlenText; $i++) {
            $ch = $text[$i];
            if (ctype_digit($ch)) {
                if ($currentDigitIndex === $posDigits) $startCharIndex = $i;
                if ($currentDigitIndex === $posDigits + $lenQueryDigits - 1) { $endCharIndex = $i; break; }
                $currentDigitIndex++;
            }
        }
        if ($startCharIndex === null || $endCharIndex === null) return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        $before = substr($text, 0, $startCharIndex);
        $middle = substr($text, $startCharIndex, $endCharIndex - $startCharIndex + 1);
        $after  = substr($text, $endCharIndex + 1);

        return htmlspecialchars($before, ENT_QUOTES, 'UTF-8')
            . '<mark>' . htmlspecialchars($middle, ENT_QUOTES, 'UTF-8') . '</mark>'
            . htmlspecialchars($after, ENT_QUOTES, 'UTF-8');
    }

    $escapedText = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $escapedQuery = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');
    $pattern = '/' . preg_quote($escapedQuery, '/') . '/i';
    return preg_replace($pattern, '<mark>$0</mark>', $escapedText);
}

function format_brasilia_datetime(?string $value, string $format = 'd/m/Y H:i'): string
{
    if (!$value) {
        return '-';
    }

    try {
        $date = new DateTime($value);
        $date->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        return $date->format($format);
    } catch (Throwable $exception) {
        return '-';
    }
}

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

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel Administrativo</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
</head>
<body>
<header class="dashboard-top">
    <div>
        <p class="badge">Administração</p>
        <h1>
            Pedidos dos Clientes
            <?php if ($isFiltered): ?>
                <span class="filter-badge" style="margin-left:8px; font-size:0.75em; padding:2px 8px; border-radius:999px; background:#eef; color:#223; vertical-align:middle;">
                    Filtros ativos
                </span>
            <?php endif; ?>
        </h1>
    </div>
    <div class="nav-actions">
        <a class="btn btn-secondary" href="logout.php">Sair</a>
    </div>
</header>

<div class="layout">

    <!-- Painel de stats (sempre totais gerais) -->
    <div class="stats-panel">
        <div>
            <span>Pedidos na fila</span>
            <strong><?= $globalTotalOrders ?></strong>
            <small>Quantidade total de pedidos</small>
            <?php if ($statusFilter === 'Cancelado'): ?>
                <small class="stat-secondary js-cancel-breakdown">
                    Cancelados: <?= $filteredOrders ?> pedido<?= $filteredOrders === 1 ? '' : 's' ?>
                </small>
            <?php endif; ?>
        </div>

        <div>
            <span>Produtos no carrinho</span>
            <strong><?= $globalTotalItems ?></strong>
            <small>Itens somados em todos os pedidos</small>
            <?php if ($statusFilter === 'Cancelado'): ?>
                <small class="stat-secondary js-cancel-breakdown">
                    Itens em pedidos cancelados: <?= (int)$filteredItems ?>
                </small>
            <?php endif; ?>
        </div>

        <div>
            <span>Faturamento Previsto</span>
            <strong><?= 'R$ ' . number_format($globalTotalValue, 2, ',', '.') ?></strong>
            <small>Total previsto considerando todos os pedidos</small>
            <?php if ($statusFilter === 'Cancelado'): ?>
                <small class="stat-secondary js-cancel-breakdown">
                    Valor dos pedidos cancelados: <?= 'R$ ' . number_format($filteredValue, 2, ',', '.') ?>
                </small>
            <?php endif; ?>
        </div>

        <div>
            <span>Total em Descontos</span>
            <strong><?= 'R$ ' . number_format($globalTotalSavings, 2, ',', '.') ?></strong>
            <small>Descontos aplicados em todos os pedidos</small>
            <?php if ($statusFilter === 'Cancelado'): ?>
                <small class="stat-secondary js-cancel-breakdown">
                    Descontos “perdidos”: <?= 'R$ ' . number_format($filteredSavings, 2, ',', '.') ?>
                </small>
            <?php endif; ?>
        </div>
    </div>

    <!-- Toggle para mostrar/ocultar impacto dos cancelados -->
    <?php if ($statusFilter === 'Cancelado'): ?>
        <div style="display:flex; justify-content:flex-end; margin:-12px 0 16px;">
            <label style="display:flex; align-items:center; gap:8px; font-size:0.9rem; color:var(--muted);">
                <input id="toggle-cancel" type="checkbox" checked>
                Mostrar impacto dos cancelados nos cards
            </label>
        </div>
    <?php endif; ?>

    <div class="filters">
        <form method="GET" class="filters-form">
            <div class="filter-field filter-search">
                <label for="filter-search">Busca</label>
                <input id="filter-search" type="search" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nome, e-mail, WhatsApp ou produto">
            </div>
            <div class="filter-field">
                <label for="filter-status">Status</label>
                <select id="filter-status" name="status">
                    <option value="">Todos</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" <?= $status === $statusFilter ? 'selected' : '' ?>>
                            <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="filter-order">Ordem</label>
                <select id="filter-order" name="order">
                    <option value="desc" <?= $orderDirection === 'DESC' ? 'selected' : '' ?>>Mais recentes</option>
                    <option value="asc" <?= $orderDirection === 'ASC' ? 'selected' : '' ?>>Mais antigos</option>
                </select>
            </div>
            <div class="filters-actions">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">Limpar</button>
            </div>
        </form>
    </div>

    <div class="results-summary" style="margin-bottom: 12px; font-size: 0.95rem; color: var(--muted, #555);">
        <?php if ($filteredOrders > 0): ?>
            <strong><?= $filteredOrders ?></strong> <?= $resultsLabel ?>
        <?php else: ?>
            Nenhum pedido encontrado
        <?php endif; ?>
        <?php if ($summaryFilters): ?>
            <span> · Filtrando por <?= implode(' e ', $summaryFilters) ?></span>
        <?php endif; ?>
    </div>

    <div class="table-wrapper">
        <?php if (!$leads): ?>
            <div class="empty-state">Nenhum pedido encontrado.</div>
        <?php else: ?>
        <table>
            <thead>
            <tr>
                <th class="col-id">#</th>
                <th class="col-client">Cliente</th>
                <th class="col-email">Email</th>
                <th class="col-whatsapp">WhatsApp</th>
                <th>Itens</th>
                <th>Investimento</th>
                <th>Economia</th>
                <th class="col-status">Status</th>
                <th class="col-entry">Entrada</th>
                <th class="col-actions">Ações</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($leads as $lead): ?>
                <tr>
                    <td class="col-id"><?= $lead['id'] ?></td>
                    <td class="col-client"><?= highlight_term($lead['full_name'] ?? '', $query) ?></td>
                    <td class="col-email"><?= highlight_term($lead['email'] ?? '', $query) ?></td>
                    <td class="col-whatsapp"><?= highlight_term($lead['whatsapp'] ?? '', $query) ?></td>
                    <td><?= (int)$lead['total_items'] ?></td>
                    <td><?= 'R$ ' . number_format($lead['total_value'], 2, ',', '.') ?></td>
                    <td><?= 'R$ ' . number_format($lead['total_savings'], 2, ',', '.') ?></td>
                    <td class="col-status">
                        <span class="status-chip <?= status_chip_class($lead['status'] ?? '') ?>">
                            <?= htmlspecialchars($lead['status'] ?? 'Em preparação interna', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                            <td class="col-entry"><?= format_brasilia_datetime($lead['created_at'] ?? null) ?></td>
                    <td class="col-actions">
                        <a class="btn btn-icon" href="order.php?id=<?= $lead['id'] ?>" aria-label="Ver detalhes">
                            <svg viewBox="0 0 24 24" role="img" aria-hidden="true">
                                <path d="M12 5c5.5 0 10 7 10 7s-4.5 7-10 7S2 12 2 12s4.5-7 10-7zm0 3a4 4 0 100 8 4 4 0 000-8zm0 2a2 2 0 110 4 2 2 0 010-4z"/>
                            </svg>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php if ($statusFilter === 'Cancelado'): ?>
<script>
document.getElementById('toggle-cancel')?.addEventListener('change', function () {
    document.querySelectorAll('.js-cancel-breakdown').forEach(function (el) {
        el.style.display = (document.getElementById('toggle-cancel').checked ? 'block' : 'none');
    });
});
</script>
<?php endif; ?>
</body>
</html>
