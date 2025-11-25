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

function format_brasilia_datetime(?string $value, string $format = 'd/m/Y H:i'): string
{
    if (!$value) return '-';
    try {
        $dt = new DateTime($value);
        $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        return $dt->format($format);
    } catch (Throwable $e) {
        return '-';
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$q = trim($_GET['q'] ?? '');
$actionFilter = trim($_GET['action'] ?? '');
$adminFilter = trim($_GET['admin_user'] ?? '');
$leadFilter = trim($_GET['lead_id'] ?? '');
$dateFrom = trim($_GET['from'] ?? '');
$dateTo = trim($_GET['to'] ?? '');

$params = [];
$where = [];

if ($q !== '') {
    $where[] = '(note LIKE :q1 OR admin_user LIKE :q2 OR action LIKE :q3)';
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
    $params[':q3'] = '%' . $q . '%';

    $actionAliases = [
        'item adicionado' => 'add_item',
        'item removido'   => 'remove_item',
        'item editado'    => 'edit_item',
        'status atualizado' => 'update_status',
    ];
    $matched = [];
    $qLower = mb_strtolower($q, 'UTF-8');
    foreach ($actionAliases as $label => $code) {
        if (strpos($qLower, $label) !== false) {
            $matched[] = $code;
        }
    }
    if ($matched) {
        $placeholders = [];
        foreach ($matched as $i => $code) {
            $ph = ':qa' . $i;
            $placeholders[] = $ph;
            $params[$ph] = $code;
        }
        $where[] = 'action IN (' . implode(',', $placeholders) . ')';
    }
}
if ($actionFilter !== '') {
    $where[] = 'action = :action';
    $params[':action'] = $actionFilter;
}
if ($adminFilter !== '') {
    $where[] = 'admin_user = :admin';
    $params[':admin'] = $adminFilter;
}
if ($leadFilter !== '') {
    $digits = preg_replace('/\D+/', '', $leadFilter);
    if ($digits !== '') {
        $where[] = 'lead_id = :lead';
        $params[':lead'] = (int)$digits;
    }
}
if ($dateFrom !== '') {
    $where[] = 'created_at >= :df';
    $params[':df'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[] = 'created_at <= :dt';
    $params[':dt'] = $dateTo . ' 23:59:59';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$actionsList = $pdo->query('SELECT DISTINCT action FROM bf_leads_log ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
$adminsList = $pdo->query('SELECT DISTINCT admin_user FROM bf_leads_log WHERE admin_user IS NOT NULL AND admin_user <> "" ORDER BY admin_user')->fetchAll(PDO::FETCH_COLUMN);

$totalLogs = (int)$pdo->query("SELECT COUNT(*) FROM bf_leads_log")->fetchColumn();

$stmtCountFiltered = $pdo->prepare("SELECT COUNT(*) FROM bf_leads_log $whereSql");
$stmtCountFiltered->execute($params);
$filteredCount = (int)$stmtCountFiltered->fetchColumn();

$stmt = $pdo->prepare("
    SELECT id, lead_id, action, item_index, note, admin_user, created_at, before_snapshot, after_snapshot
    FROM bf_leads_log
    $whereSql
    ORDER BY created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$summaryStmt = $pdo->query("
    SELECT action, COUNT(*) AS qty
    FROM bf_leads_log
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY action
    ORDER BY qty DESC
");
$summary = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

$lastActionAt = $pdo->query("SELECT created_at FROM bf_leads_log ORDER BY created_at DESC LIMIT 1")->fetchColumn();

function human_action(?string $action): string
{
    $map = [
        'add_item' => 'Item adicionado',
        'edit_item' => 'Item editado',
        'remove_item' => 'Item removido',
        'update_status' => 'Status atualizado',
    ];
    if (!$action) return '-';
    return $map[$action] ?? ucfirst(str_replace('_', ' ', $action));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Histórico de alterações | Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
</head>
<body>
<header class="dashboard-top">
    <div>
        <p class="badge">Administração</p>
        <h1>Histórico de alterações</h1>
        <p class="muted">
            <?= $filteredCount ?> registro<?= $filteredCount === 1 ? '' : 's' ?> listado<?= $filteredCount === 1 ? '' : 's' ?> de <?= $totalLogs ?> total.
            <?php if ($lastActionAt): ?>
                Última ação: <?= format_brasilia_datetime($lastActionAt) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="nav-actions">
        <a class="btn btn-secondary" href="index.php">Voltar ao painel</a>
        <a class="btn btn-secondary" href="logout.php">Sair</a>
    </div>
</header>

<div class="layout">
    <div class="stats-panel">
        <div>
            <span>Última ação</span>
            <strong><?= $lastActionAt ? format_brasilia_datetime($lastActionAt) : '-' ?></strong>
            <small>Horário mais recente registrado</small>
        </div>
        <div>
            <span>Total de logs</span>
            <strong><?= $totalLogs ?></strong>
            <small>Todos os registros</small>
        </div>
        <div>
            <span>Registros filtrados</span>
            <strong><?= $filteredCount ?></strong>
            <small>Resultados da busca atual</small>
        </div>
        <div>
            <span>Ações em 7 dias</span>
            <strong><?= array_sum(array_column($summary, 'qty')) ?: 0 ?></strong>
            <small>Soma das ações na última semana</small>
        </div>
    </div>

    <?php if ($summary): ?>
            <div class="detail-card">
                <div class="detail-grid">
                    <?php foreach ($summary as $row): ?>
                        <div>
                            <span><?= htmlspecialchars(human_action($row['action'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span><br>
                            <strong><?= (int)$row['qty'] ?> registro<?= ((int)$row['qty'] === 1) ? '' : 's' ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
        </div>
    <?php endif; ?>

    <div class="filters">
        <form method="GET" class="filters-form">
            <div class="filter-field filter-search">
                <label for="f-q">Busca</label>
                <input id="f-q" type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ação, admin, nota">
            </div>
            <div class="filter-field">
                <label for="f-action">Ação</label>
                <select id="f-action" name="action">
                    <option value="">Todas</option>
                    <?php foreach ($actionsList as $act): ?>
                        <option value="<?= htmlspecialchars($act, ENT_QUOTES, 'UTF-8') ?>" <?= $act === $actionFilter ? 'selected' : '' ?>><?= htmlspecialchars(human_action($act), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="f-admin">Admin</label>
                <select id="f-admin" name="admin_user">
                    <option value="">Todos</option>
                    <?php foreach ($adminsList as $adm): ?>
                        <option value="<?= htmlspecialchars($adm, ENT_QUOTES, 'UTF-8') ?>" <?= $adm === $adminFilter ? 'selected' : '' ?>><?= htmlspecialchars($adm, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="f-lead">Pedido #</label>
                <input id="f-lead" type="number" name="lead_id" value="<?= htmlspecialchars($leadFilter, ENT_QUOTES, 'UTF-8') ?>" min="1" placeholder="ex: 12">
            </div>
            <div class="filter-field">
                <label>De</label>
                <input type="date" name="from" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="filter-field">
                <label>Até</label>
                <input type="date" name="to" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="filters-actions">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='logs.php'">Limpar</button>
            </div>
        </form>
    </div>

    <div class="table-wrapper">
        <?php if (!$logs): ?>
            <div class="empty-state">Nenhum registro encontrado.</div>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>#</th>
                    <th>Ação</th>
                    <th>Pedido</th>
                    <th>Item</th>
                    <th>Nota</th>
                    <th>Admin</th>
                    <th>Data/Hora</th>
                    <th>Detalhes</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= (int)$log['id'] ?></td>
                        <td><span class="badge"><?= htmlspecialchars(human_action($log['action'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td>
                            <?php if (!empty($log['lead_id'])): ?>
                                <a href="order.php?id=<?= (int)$log['lead_id'] ?>">#<?= (int)$log['lead_id'] ?></a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= isset($log['item_index']) ? htmlspecialchars((string)$log['item_index'], ENT_QUOTES, 'UTF-8') : '-' ?></td>
                        <td><?= htmlspecialchars($log['note'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($log['admin_user'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= format_brasilia_datetime($log['created_at'] ?? null, 'd/m/Y H:i:s') ?></td>
                        <td>
                            <details>
                                <summary>Ver</summary>
                                <div class="log-detail">
                                    <div>
                                        <strong>Antes</strong>
                                        <pre><?= htmlspecialchars($log['before_snapshot'] ?? '', ENT_QUOTES, 'UTF-8') ?></pre>
                                    </div>
                                    <div>
                                        <strong>Depois</strong>
                                        <pre><?= htmlspecialchars($log['after_snapshot'] ?? '', ENT_QUOTES, 'UTF-8') ?></pre>
                                    </div>
                                </div>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if ($filteredCount > $limit): ?>
        <div class="pagination">
            <?php $totalPages = (int)ceil($filteredCount / $limit); ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a class="page-link <?= $p === $page ? 'is-active' : '' ?>" href="<?= htmlspecialchars('?' . http_build_query(array_merge($_GET, ['page' => $p])), ENT_QUOTES, 'UTF-8') ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
