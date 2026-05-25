<?php
require_once '../../config/database.php';
require_once '../../helpers/mailer.php';

$email = trim($_POST['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: forgot_password.php?error=' . urlencode('Format email tidak valid.'));
    exit;
}

// Find user by email
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

// Always show success even if email not found (security best practice)
if (!$user) {
    header('Location: forgot_password.php?success=1');
    exit;
}

// Generate reset token
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

$pdo->prepare("UPDATE users SET password_token = ?, token_expires_at = ? WHERE id = ?")
    ->execute([$token, $expiresAt, $user['id']]);

// Send reset email
$resetUrl = "http://" . $_SERVER['HTTP_HOST'] . "/../modules/auth/reset_password.php?token=$token";

$bodyHtml = "
    <p style='color:#444;font-size:13px;margin:0 0 16px'>Halo <strong>{$user['name']}</strong>,</p>
    <p style='color:#444;font-size:13px;margin:0 0 16px'>Kami menerima permintaan reset password untuk akun kamu di sistem <strong>4M Change</strong>. Klik tombol di bawah untuk mengatur password baru.</p>
    <table style='width:100%;border-collapse:collapse;font-size:13px;margin-bottom:16px'>
        <tr><td style='color:#888;padding:6px 0;width:120px'>Username</td><td style='padding:6px 0;font-weight:600;font-family:monospace'>{$user['username']}</td></tr>
        <tr><td style='color:#888;padding:6px 0'>Email</td><td style='padding:6px 0'>{$user['email']}</td></tr>
    </table>
    <div style='margin:20px 0'>
        <a href='$resetUrl' style='background:#D0021B;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600'>Reset Password Saya</a>
    </div>
    <p style='color:#888;font-size:12px;margin:16px 0 0'>Link ini berlaku selama <strong>1 jam</strong>. Jika kamu tidak merasa meminta reset password, abaikan email ini.</p>";

sendMail($email, $user['name'], '[4M Change] Reset Password', mailTemplate('Reset Password', $bodyHtml));

header('Location: forgot_password.php?success=1');
exit;