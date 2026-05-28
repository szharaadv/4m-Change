<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
require_once '../../helpers/mailer.php';
require_once '../../helpers/audit.php';
requireRole(['admin', 'superadmin']);

$name = trim($_POST['name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = $_POST['role'] ?? '';

if (!$name || !$username || !$email || !$role) {
    header('Location: create.php?error=' . urlencode('Semua field wajib diisi.'));
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: create.php?error=' . urlencode('Format email tidak valid.'));
    exit;
}

if (!in_array($role, ['admin', 'manager', 'qc'], true)) {
    header('Location: create.php?error=' . urlencode('Role tidak valid.'));
    exit;
}

$check = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$check->execute([$username]);
if ($check->fetch()) {
    header('Location: create.php?error=' . urlencode('Username sudah digunakan.'));
    exit;
}

$checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$checkEmail->execute([$email]);
if ($checkEmail->fetch()) {
    header('Location: create.php?error=' . urlencode('Email sudah digunakan.'));
    exit;
}

$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

try {
    $pdo->prepare("INSERT INTO users (name, username, email, password, role, password_token, token_expires_at, is_active) VALUES (?, ?, ?, '', ?, ?, ?, 0)")
        ->execute([$name, $username, $email, $role, $token, $expiresAt]);

    writeAuditLog($pdo, 'USER_CREATED', "Tambah user baru: $username ($role) — $email");

    $setupUrl = "http://" . $_SERVER['HTTP_HOST'] . "/../modules/auth/set_password.php?token=$token";

    $bodyHtml = "
        <p style='color:#444;font-size:13px;margin:0 0 16px'>Halo <strong>$name</strong>,</p>
        <p style='color:#444;font-size:13px;margin:0 0 16px'>Akun kamu di sistem <strong>4M Change</strong> telah dibuat oleh Administrator. Klik tombol di bawah untuk mengatur password kamu.</p>
        <table style='width:100%;border-collapse:collapse;font-size:13px;margin-bottom:16px'>
            <tr><td style='color:#888;padding:6px 0;width:120px'>Username</td><td style='padding:6px 0;font-weight:600;font-family:monospace'>$username</td></tr>
            <tr><td style='color:#888;padding:6px 0'>Role</td><td style='padding:6px 0'>$role</td></tr>
        </table>
        <div style='margin:20px 0'>
            <a href='$setupUrl' style='background:#D0021B;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600'>Set Password Saya</a>
        </div>
        <p style='color:#888;font-size:12px;margin:16px 0 0'>Link ini berlaku selama <strong>24 jam</strong>. Jika kamu tidak merasa mendaftar, abaikan email ini.</p>";

    sendMail($email, $name, '[4M Change] Setup Password Akun Baru', mailTemplate('Setup Password Akun Anda', $bodyHtml));

    header('Location: index.php?success=created');
    exit;
} catch (Exception $e) {
    header('Location: create.php?error=' . urlencode($e->getMessage()));
    exit;
}