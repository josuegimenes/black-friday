<?php
require __DIR__ . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');
if (function_exists('ob_get_level')) {
    while (ob_get_level()) {
        ob_end_clean();
    }
}

function time_ago_br(string $datetime): string
{
    try {
        $tz = new DateTimeZone('America/Sao_Paulo');
        $dt = new DateTime($datetime, $tz);
    } catch (Throwable $e) {
        return 'agora mesmo';
    }

    $now = new DateTime('now', $tz);
    $diff = $now->getTimestamp() - $dt->getTimestamp();
    if ($diff < 60) return 'agora mesmo';
    if ($diff < 3600) return 'há ' . max(1, floor($diff / 60)) . ' min';
    if ($diff < 86400) return 'há ' . max(1, floor($diff / 3600)) . 'h';
    return $dt->format('d/m H:i');
}

function abrevia_nome(string $nome): string
{
    $parts = array_values(array_filter(array_map('trim', explode(' ', $nome))));
    if (count($parts) >= 2) {
        return $parts[0] . ' ' . mb_substr($parts[1], 0, 1, 'UTF-8') . '.';
    }
    return $nome ?: 'Cliente';
}

$pdo = null;
$items = [];

try {
    $pdo = get_pdo();
    $stmt = $pdo->query('SELECT full_name, created_at, total_items FROM bf_leads ORDER BY id DESC LIMIT 10');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $items[] = [
            'name' => abrevia_nome($row['full_name'] ?? 'Cliente'),
            'time' => time_ago_br($row['created_at'] ?? 'now'),
            'pieces' => max(1, (int)($row['total_items'] ?? 1)),
        ];
    }
    if (count($items) > 1) {
        shuffle($items);
    }
} catch (Throwable $exception) {
    error_log('activity.php - falha ao buscar leads: ' . $exception->getMessage());
}

$fallback = [
    ['name' => 'Ana Souza'],
    ['name' => 'Bruna Lima'],
    ['name' => 'Camila Rocha'],
    ['name' => 'Daniele Martins'],
    ['name' => 'Eduarda Silva'],
    ['name' => 'Fernanda Santos'],
    ['name' => 'Gabriela Costa'],
    ['name' => 'Helena Freitas'],
    ['name' => 'Isabela Nunes'],
    ['name' => 'Juliana Pedroso'],
    ['name' => 'Larissa Almeida'],
    ['name' => 'Mariana Paulo'],
    ['name' => 'Natalia Ribeiro'],
    ['name' => 'Olivia Teixeira'],
    ['name' => 'Patricia Gomes'],
    ['name' => 'Queila Andrade'],
    ['name' => 'Rafaela Monteiro'],
    ['name' => 'Sabrina Carvalho'],
    ['name' => 'Tania Vieira'],
    ['name' => 'Ursula Campos'],
    ['name' => 'Vanessa Prado'],
    ['name' => 'Wanessa Melo'],
    ['name' => 'Yasmin Faria'],
    ['name' => 'Zelia Duarte'],
    ['name' => 'Alice Moura'],
    ['name' => 'Bianca Araujo'],
    ['name' => 'Clara Barros'],
    ['name' => 'Daniela Campos'],
    ['name' => 'Elisa Rezende'],
    ['name' => 'Flavia Lopes'],
    ['name' => 'Geovana Costa'],
    ['name' => 'Heloisa Pires'],
    ['name' => 'Ingrid Carvalho'],
    ['name' => 'Joyce Fernandes'],
    ['name' => 'Karen Teles'],
    ['name' => 'Leticia Fonseca'],
    ['name' => 'Manuella Prado'],
    ['name' => 'Nicole Dias'],
    ['name' => 'Paola Soares'],
    ['name' => 'Rebeca Lemos'],
    ['name' => 'Stella Marques'],
    ['name' => 'Talita Xavier'],
    ['name' => 'Valentina Nogueira'],
    ['name' => 'Vivian Cardoso'],
    ['name' => 'Adriana Torres'],
    ['name' => 'Mirella Bastos'],
    ['name' => 'Joana Silveira'],
    ['name' => 'Livia Brito'],
    ['name' => 'Melissa Assis'],
    ['name' => 'Barbara Moretti'],
];

// Preenche até 10 com fallback inventado se necessário
shuffle($fallback);
while (count($items) < 10 && !empty($fallback)) {
    $fake = array_shift($fallback);
    $items[] = [
        'name' => abrevia_nome($fake['name']),
        'time' => 'agora mesmo',
        'pieces' => rand(1, 5),
        'fake' => true,
    ];
}

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
