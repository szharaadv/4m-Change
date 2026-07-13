<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
require_once '../../helpers/mailer.php';
requireRole(['admin', 'superadmin']);

$id = (int) ($_POST['id'] ?? 0);

if (!$id) {
    header('Location: index.php?error=' . urlencode('User not found.'));
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 0 LIMIT 1");
$stmt->execute([$id]);
$u = $stmt->fetch();

if (!$u) {
    header('Location: index.php?error=' . urlencode('User not found or already active.'));
    exit;
}

if (!$u['email']) {
    header('Location: index.php?error=' . urlencode('User does not have an email address.'));
    exit;
}

// Generate new token
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

$pdo->prepare("UPDATE users SET password_token = ?, token_expires_at = ? WHERE id = ?")
    ->execute([$token, $expiresAt, $id]);

// Send setup email
$setupUrl = APP_URL . "/modules/auth/set_password.php?token=$token";    

$bodyHtml = mailGreeting($u['name']) . "
    <p style='margin:0 0 14px'>This is a resend of your account password setup email for the <strong>4M Change</strong> system. Click the button below to set your password.</p>
    " . mailInfoTable(['Username' => "<span style='font-family:monospace'>{$u['username']}</span>", 'Role' => $u['role']]) . "
    " . mailButton($setupUrl, 'Set My Password') . "
    <p style='margin:14px 0 0;font-size:12.5px;color:#6b7280'>This link is valid for <strong>24 hours</strong>. If you did not expect this, please ignore this email.</p>";

sendMail($u['email'], $u['name'], '[4M Change] Setup Password — Resend', mailTemplate('Setup Your Account Password', $bodyHtml));

header('Location: index.php?success=email_resent');
exit;