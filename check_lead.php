<?php
require __DIR__ . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');

$email = mb_strtolower(trim($_GET['email'] ?? ''), 'UTF-8');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    $pdo = get_pdo();
$stmt = $pdo->prepare('SELECT id, full_name, total_items, total_value, total_savings, status, created_at FROM bf_leads WHERE LOWER(email) = :email ORDER BY id DESC LIMIT 1');
    $stmt->execute([':email' => $email]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lead) {
        echo json_encode(['exists' => false]);
        exit;
    }

    $createdAt = $lead['created_at'] ?? null;
    if ($createdAt) {
        try {
            $date = new DateTime($createdAt);
            $date->setTimezone(new DateTimeZone('America/Sao_Paulo'));
            $createdAt = $date->format('d/m/Y H:i');
        } catch (Throwable $e) {
            // keep original value
        }
    }

    echo json_encode([
        'exists' => true,
        'lead' => [
            'id' => (int) $lead['id'],
            'full_name' => $lead['full_name'],
            'total_items' => (int) $lead['total_items'],
            'total_value' => (float) $lead['total_value'],
            'total_savings' => (float) $lead['total_savings'],
            'created_at' => $lead['created_at'],
            'created_at_br' => $createdAt,
            'status' => $lead['status'] ?? 'Em preparação interna',
        ],
    ]);
} catch (Throwable $exception) {
    error_log('Erro ao consultar lead: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Nao foi possivel consultar pedidos anteriores.']);
}
