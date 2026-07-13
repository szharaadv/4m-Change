<?php
session_start();
require_once '../../config/database.php';
require_once '../../helpers/audit.php';

$token = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

if (!$token) {
    header('Location: set_password.php?token=&error=' . urlencode('Invalid token.'));
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE password_token = ? AND token_expires_at > NOW() AND is_active = 0 LIMIT 1");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center">
        <h2>Invalid or expired link.</h2>
        <p>Please contact the administrator to get a new link.</p>
    </div>');
}

if (strlen($password) < 6) {
    header('Location: set_password.php?token=' . urlencode($token) . '&error=' . urlencode('Password must be at least 6 characters.'));
    exit;
}

if ($password !== $passwordConfirm) {
    header('Location: set_password.php?token=' . urlencode($token) . '&error=' . urlencode('Password and confirmation do not match.'));
    exit;
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$pdo->prepare("UPDATE users SET password = ?, password_token = NULL, token_expires_at = NULL, is_active = 1 WHERE id = ?")
    ->execute([$hashedPassword, $user['id']]);

// Auto login
$_SESSION['user'] = [
    'id' => $user['id'],
    'name' => $user['name'],
    'username' => $user['username'],
    'role' => $user['role'],
];

// Audit log after session set
writeAuditLog($pdo, 'PASSWORD_SET', "User {$user['username']} set password for the first time & account activated", $user['id'], $user['username'], $user['role']);

header('Location: ' . navLink('modules/dashboard/index.php'));
exit;