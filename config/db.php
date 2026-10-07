<?php
function get_pdo(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['host'], $config['database'], $config['charset']);

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $config['user'], $config['password'], $options);
    return $pdo;
}
?>
