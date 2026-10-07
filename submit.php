<?php
require __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$whatsapp = trim($_POST['whatsapp'] ?? '');
$cartPayload = $_POST['cart_payload'] ?? '';

if ($fullName === '' || $email === '' || $whatsapp === '' || $cartPayload === '') {
    http_response_code(422);
    echo 'Preencha todos os campos e selecione pelo menos um produto.';
    exit;
}

$cartData = json_decode($cartPayload, true);
if (!is_array($cartData) || empty($cartData['items'])) {
    http_response_code(422);
    echo 'Não encontramos os produtos do carrinho. Tente novamente.';
    exit;
}

$totals = $cartData['totals'] ?? [];
$totalItems = (int)($totals['totalItems'] ?? 0);
$totalValue = (float)($totals['totalValue'] ?? 0);
$totalSavings = (float)($totals['totalSavings'] ?? 0);

try {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('INSERT INTO bf_leads (full_name, email, whatsapp, products, total_items, total_value, total_savings) VALUES (:name, :email, :whatsapp, :products, :total_items, :total_value, :total_savings)');
    $stmt->execute([
        ':name' => $fullName,
        ':email' => $email,
        ':whatsapp' => $whatsapp,
        ':products' => json_encode($cartData, JSON_UNESCAPED_UNICODE),
        ':total_items' => $totalItems,
        ':total_value' => $totalValue,
        ':total_savings' => $totalSavings,
    ]);
} catch (Throwable $exception) {
    error_log('Erro ao salvar lead: ' . $exception->getMessage());
    http_response_code(500);
    echo 'Não conseguimos registrar sua reserva agora. Atualize a página e tente novamente.';
    exit;
}

header('Location: obrigado.php?name=' . urlencode($fullName));
exit;
