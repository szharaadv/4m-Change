<?php
require_once __DIR__ . '/paths.php';

$host    = '127.0.0.1';
$db      = 'db_4m_change';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Database Connection Failed: ' . htmlspecialchars($e->getMessage()));
}

$isLocal = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']);
define('APP_URL', $isLocal ? 'http://localhost/4m-change' : 'https://4m-change.yadin.com');