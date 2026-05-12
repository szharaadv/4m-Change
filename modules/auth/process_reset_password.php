<?php
session_start();
require_once '../../config/database.php';
require_once '../../helpers/audit.php';

$token = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

if (!$token) {
    header('Location: forgot_password.php?error=' . urlencode('Token tidak valid.'));
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE password_token = ? AND token_expires_at > NOW() AND is_active = 1 LIMIT 1");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center">
        <h2>Link tidak valid atau sudah kadaluarsa.</h2>
        <p>Silakan request reset password lagi.</p>
        <a href="forgot_password.php">Request Ulang</a>
    </div>');
}

if (strlen($password) < 6) {
    header('Location: reset_password.php?token=' . urlencode($token) . '&error=' . urlencode('Password minimal 6 karakter.'));
    exit;
}

if ($password !== $passwordConfirm) {
    header('Location: reset_password.php?token=' . urlencode($token) . '&error=' . urlencode('Password dan konfirmasi tidak cocok.'));
    exit;
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$pdo->prepare("UPDATE users SET password = ?, password_token = NULL, token_expires_at = NULL WHERE id = ?")
    ->execute([$hashedPassword, $user['id']]);

// Auto login
$_SESSION['user'] = [
    'id' => $user['id'],
    'name' => $user['name'],
    'username' => $user['username'],
    'role' => $user['role'],
];

// Audit log after session set
writeAuditLog($pdo, 'PASSWORD_RESET', "User {$user['username']} reset password via email", $user['id'], $user['username'], $user['role']);

header('Location: /4m-change/modules/dashboard/index.php');
exit;