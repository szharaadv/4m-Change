<?php
require_once '../../config/database.php';
require_once '../../helpers/mailer.php';

$email = trim($_POST['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: forgot_password.php?error=' . urlencode('Invalid email format.'));
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
$resetUrl = APP_URL . "/modules/auth/reset_password.php?token=$token";

$bodyHtml = mailGreeting($user['name']) . "
    <p style='margin:0 0 14px'>We received a request to reset the password for your account on the <strong>4M Change</strong> system. Click the button below to set a new password.</p>
    " . mailInfoTable(['Username' => "<span style='font-family:monospace'>{$user['username']}</span>", 'Email' => $user['email']]) . "
    " . mailButton($resetUrl, 'Reset My Password') . "
    <p style='margin:14px 0 0;font-size:12.5px;color:#6b7280'>This link is valid for <strong>1 hour</strong>. If you did not request a password reset, please ignore this email.</p>";

sendMail($email, $user['name'], '[4M Change] Reset Password', mailTemplate('Reset Password', $bodyHtml));

header('Location: forgot_password.php?success=1');
exit;