<?php
$host = 'sql200.infinityfree.com';
$db   = 'if0_39048119_bmi';
$user = 'if0_39048119';
$pass = 'luminomot23';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Ideally, log this error and show a generic message
    // For now, we might want to see it for debugging
    // throw new \PDOException($e->getMessage(), (int)$e->getCode());
    die("Database connection failed: " . $e->getMessage());
}
?>