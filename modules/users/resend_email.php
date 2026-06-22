<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
require_once '../../helpers/mailer.php';
requireRole(['admin', 'superadmin']);

$id = (int) ($_POST['id'] ?? 0);

if (!$id) {
    header('Location: index.php?error=' . urlencode('User tidak ditemukan.'));
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 0 LIMIT 1");
$stmt->execute([$id]);
$u = $stmt->fetch();

if (!$u) {
    header('Location: index.php?error=' . urlencode('User tidak ditemukan atau sudah aktif.'));
    exit;
}

if (!$u['email']) {
    header('Location: index.php?error=' . urlencode('User tidak memiliki email.'));
    exit;
}

// Generate new token
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

$pdo->prepare("UPDATE users SET password_token = ?, token_expires_at = ? WHERE id = ?")
    ->execute([$token, $expiresAt, $id]);

// Send setup email
$setupUrl = APP_URL . "/modules/auth/set_password.php?token=$token";    

$bodyHtml = "
    <p style='color:#444;font-size:13px;margin:0 0 16px'>Halo <strong>{$u['name']}</strong>,</p>
    <p style='color:#444;font-size:13px;margin:0 0 16px'>Ini adalah email pengiriman ulang untuk setup password akun kamu di sistem <strong>4M Change</strong>. Klik tombol di bawah untuk mengatur password kamu.</p>
    <table style='width:100%;border-collapse:collapse;font-size:13px;margin-bottom:16px'>
        <tr><td style='color:#888;padding:6px 0;width:120px'>Username</td><td style='padding:6px 0;font-weight:600;font-family:monospace'>{$u['username']}</td></tr>
        <tr><td style='color:#888;padding:6px 0'>Role</td><td style='padding:6px 0'>{$u['role']}</td></tr>
    </table>
    <div style='margin:20px 0'>
        <a href='$setupUrl' style='background:#D0021B;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600'>Set Password Saya</a>
    </div>
    <p style='color:#888;font-size:12px;margin:16px 0 0'>Link ini berlaku selama <strong>24 jam</strong>. Jika kamu tidak merasa mendaftar, abaikan email ini.</p>";

sendMail($u['email'], $u['name'], '[4M Change] Setup Password — Pengiriman Ulang', mailTemplate('Setup Password Akun Anda', $bodyHtml));

header('Location: index.php?success=email_resent');
exit;