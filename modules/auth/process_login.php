<?php
session_start();
require_once '../../config/database.php';
require_once '../../helpers/audit.php';

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Support plain text password (dev) and hashed password (production)
$valid = $user && (
    (strlen($user['password']) > 30 && password_verify($password, $user['password']))
);

if ($valid && $user['is_active'] == 1) {
    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'username' => $user['username'],
        'role' => $user['role'],
    ];

    writeAuditLog($pdo, 'LOGIN', 'Login berhasil', $user['id'], $user['username'], $user['role']);

    header('Location: /../../modules/dashboard/index.php');
    exit;
}

// Log failed login
writeAuditLog($pdo, 'LOGIN_FAILED', 'Login gagal — username: ' . $username, null, $username, null);

header('Location: login.php?error=1');
exit;