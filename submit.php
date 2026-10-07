<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/vendor/phpmailer/autoload.php';
require __DIR__ . '/templates/email/reservation-confirmation.php';

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

const WHATSAPP_GROUP_LINK = 'https://chat.whatsapp.com/CrFlwHsXKoOKTkX9kqvdJM';

function base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'vesteme.com.br';

    // Caminho do script atual, ex: /black-friday/submit.php
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/.');

    // Se estiver na raiz, $dir fica vazio
    $basePath = $dir ? $dir : '';

    return rtrim($scheme . '://' . $host . $basePath, '/');
}

function format_currency(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function resolve_media_url(?string $path): ?string
{
    if (!$path) {
        return null;
    }

    $trimmed = trim($path);
    if ($trimmed === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $trimmed)) {
        return $trimmed;
    }

    return base_url() . '/' . ltrim($trimmed, '/');
}

function format_brasilia_datetime(string $format = 'd/m/Y H:i'): string
{
    $tz = new DateTimeZone('America/Sao_Paulo');
    $date = new DateTime('now', $tz);
    return $date->format($format);
}

function load_mailer_config(): ?array
{
    $path = __DIR__ . '/config/mailer.php';
    if (!is_file($path)) {
        error_log('Arquivo config/mailer.php nao encontrado. O e-mail nao sera disparado.');
        return null;
    }
    $config = require $path;
    if (!is_array($config)) {
        error_log('config/mailer.php precisa retornar um array valido.');
        return null;
    }
    return $config;
}

function fetch_latest_lead(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM bf_leads WHERE LOWER(email) = :email ORDER BY id DESC LIMIT 1');
    $stmt->execute([':email' => $email]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    return $lead ?: null;
}

function merge_cart_payload(array $existingPayload, array $incomingPayload): array
{
    $existingItems = $existingPayload['items'] ?? [];
    $existingTotals = $existingPayload['totals'] ?? [];
    $incomingTotals = $incomingPayload['totals'] ?? [];

    return [
        'items' => array_merge($existingItems, $incomingPayload['items'] ?? []),
        'totals' => [
            'totalItems' => (int)($existingTotals['totalItems'] ?? 0) + (int)($incomingTotals['totalItems'] ?? 0),
            'totalValue' => (float)($existingTotals['totalValue'] ?? 0) + (float)($incomingTotals['totalValue'] ?? 0),
            'totalSavings' => (float)($existingTotals['totalSavings'] ?? 0) + (float)($incomingTotals['totalSavings'] ?? 0),
        ],
    ];
}

function create_mailer(array $config): PHPMailer
{
    $mailer = new PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $config['host'] ?? '';
    $mailer->Port = (int)($config['port'] ?? 587);
    $mailer->SMTPAuth = true;
    $mailer->Username = $config['username'] ?? '';
    $mailer->Password = $config['password'] ?? '';
    $encryption = strtolower((string)($config['encryption'] ?? 'tls'));
    if ($encryption === 'ssl') {
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($encryption === 'none' || $encryption === '') {
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    $mailer->CharSet = 'UTF-8';
    $mailer->Timeout = (int)($config['timeout'] ?? 15);
    $mailer->setFrom(
        $config['from_email'] ?? 'contato@vesteme.com.br',
        $config['from_name'] ?? 'Vesteme Black'
    );
    $mailer->addReplyTo($config['from_email'] ?? 'contato@vesteme.com.br', $config['from_name'] ?? 'Vesteme Black');
    if (!empty($config['debug'])) {
        $mailer->SMTPDebug = (int) $config['debug'];
        $mailer->Debugoutput = static function ($str, $level) {
            error_log("PHPMailer [{$level}]: {$str}");
        };
    }
    return $mailer;
}
function build_email_body(string $name, array $cartData, string $logoUrl, string $groupLink): string
{
    $totals = $cartData['totals'] ?? [];
    $items = [];
    foreach ($cartData['items'] as $item) {
        $quantity = (int)($item['quantity'] ?? 0);
        $unitPrice = (float)($item['salePrice'] ?? 0);
        $lineTotal = $unitPrice * max(1, $quantity);
        $items[] = [
            'name' => $item['name'] ?? 'Produto',
            'color' => $item['color'] ?? '-',
            'size' => $item['size'] ?? '-',
            'quantity' => $quantity,
            'line_total' => format_currency($lineTotal),
            'thumb' => resolve_media_url($item['thumb'] ?? null),
        ];
    }

    $payload = [
        'name' => $name,
        'date_br' => format_brasilia_datetime(),
        'logo_url' => $logoUrl,
        'group_link' => $groupLink,
        'items' => $items,
        'totals' => [
            'items' => (string)((int)($totals['totalItems'] ?? 0)),
            'value' => format_currency((float)($totals['totalValue'] ?? 0)),
            'savings' => format_currency((float)($totals['totalSavings'] ?? 0)),
        ],
    ];

    return render_reservation_email($payload);
}

function send_confirmation_email(string $to, string $name, array $cartData, array $config): void
{
    if (empty($config['host']) || empty($config['username']) || empty($config['password'])) {
        error_log('Config SMTP incompleta. E-mail nao enviado.');
        return;
    }

    $logoUrl = base_url() . '/assets/img/logo-vesteme-black.jpg';
    $body = build_email_body($name, $cartData, $logoUrl, WHATSAPP_GROUP_LINK);
    $subject = 'Parabéns! Sua reserva VIP foi confirmada 🎉';
    $altBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

    try {
        $mailer = create_mailer($config);
        $mailer->addAddress($to, $name);
        $mailer->isHTML(true);
        $mailer->Subject = $subject;
        $mailer->Body = $body;
        $mailer->AltBody = $altBody;
        $mailer->send();
    } catch (PHPMailerException $exception) {
        error_log('Erro ao enviar e-mail via PHPMailer: ' . $exception->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$emailRaw = trim($_POST['email'] ?? '');
$email = mb_strtolower($emailRaw, 'UTF-8');
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
    echo 'Nao encontramos os produtos do carrinho. Tente novamente.';
    exit;
}

$totals = $cartData['totals'] ?? [];
$totalItems = (int)($totals['totalItems'] ?? 0);
$totalValue = (float)($totals['totalValue'] ?? 0);
$totalSavings = (float)($totals['totalSavings'] ?? 0);
$mergeStrategy = $_POST['merge_previous'] ?? 'no';

try {
    $pdo = get_pdo();
    $existingLead = $mergeStrategy === 'yes' ? fetch_latest_lead($pdo, $email) : null;

    if ($mergeStrategy === 'yes' && $existingLead) {
        $existingPayload = json_decode($existingLead['products'], true);
        if (!is_array($existingPayload)) {
            $existingPayload = ['items' => [], 'totals' => ['totalItems' => 0, 'totalValue' => 0, 'totalSavings' => 0]];
        }
        $cartData = merge_cart_payload($existingPayload, $cartData);
        $totals = $cartData['totals'] ?? [];
        $totalItems = (int)($totals['totalItems'] ?? 0);
        $totalValue = (float)($totals['totalValue'] ?? 0);
        $totalSavings = (float)($totals['totalSavings'] ?? 0);

        $stmt = $pdo->prepare('UPDATE bf_leads SET full_name = :name, whatsapp = :whatsapp, products = :products, total_items = :total_items, total_value = :total_value, total_savings = :total_savings WHERE id = :id');
        $stmt->execute([
            ':name' => $fullName,
            ':whatsapp' => $whatsapp,
            ':products' => json_encode($cartData, JSON_UNESCAPED_UNICODE),
            ':total_items' => $totalItems,
            ':total_value' => $totalValue,
            ':total_savings' => $totalSavings,
            ':id' => $existingLead['id'],
        ]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO bf_leads (full_name, email, whatsapp, products, total_items, total_value, total_savings, status) VALUES (:name, :email, :whatsapp, :products, :total_items, :total_value, :total_savings, :status)');
        $stmt->execute([
            ':name' => $fullName,
            ':email' => $email,
            ':whatsapp' => $whatsapp,
            ':products' => json_encode($cartData, JSON_UNESCAPED_UNICODE),
            ':total_items' => $totalItems,
            ':total_value' => $totalValue,
            ':total_savings' => $totalSavings,
            ':status' => 'Em preparação interna',
        ]);
    }
} catch (Throwable $exception) {
    error_log('Erro ao salvar lead: ' . $exception->getMessage());
    http_response_code(500);
    echo 'Nao conseguimos registrar sua reserva agora. Atualize a pagina e tente novamente.';
    exit;
}

$mailerConfig = load_mailer_config();
if ($mailerConfig !== null) {
    try {
        send_confirmation_email($emailRaw ?: $email, $fullName, $cartData, $mailerConfig);
    } catch (Throwable $exception) {
        error_log('Erro ao enviar e-mail: ' . $exception->getMessage());
    }
} else {
    error_log('Config de e-mail nao carregada. Nenhuma mensagem foi enviada.');
}

header('Location: obrigado.php?name=' . urlencode($fullName));
exit;
