<?php
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
    die('<div style="font-family:sans-serif;padding:40px;background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;border-radius:8px;margin:40px auto;max-width:600px">
        <h2>Database Connection Failed</h2>
        <p>' . htmlspecialchars($e->getMessage()) . '</p>
        <p style="font-size:13px;margin-top:12px">Pastikan XAMPP MySQL sudah berjalan dan database <strong>db_4m_change</strong> sudah dibuat.</p>
    </div>');
}
