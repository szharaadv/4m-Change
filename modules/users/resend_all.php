<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
require_once '../../helpers/mailer.php';
require_once '../../helpers/audit.php';
requireRole(['superadmin']);

$stmt = $pdo->query("SELECT * FROM users WHERE is_active = 0 AND email != '' AND email IS NOT NULL");
$pendingUsers = $stmt->fetchAll();

if (empty($pendingUsers)) {
    header('Location: index.php?error=' . urlencode('No pending users found.'));
    exit;
}

$success = 0;
$failed  = 0;

foreach ($pendingUsers as $u) {
    // Generate new token
    $token     = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $pdo->prepare("UPDATE users SET password_token = ?, token_expires_at = ? WHERE id = ?")
        ->execute([$token, $expiresAt, $u['id']]);

    $setupUrl = APP_URL . "/modules/auth/set_password.php?token=$token";

    $bodyHtml = mailGreeting($u['name']) . "
        <p style='margin:0 0 14px'>This is a resend of your account password setup email for the <strong>4M Change</strong> system.</p>
        " . mailInfoTable(['Username' => "<span style='font-family:monospace'>{$u['username']}</span>", 'Role' => $u['role']]) . "
        " . mailButton($setupUrl, 'Set My Password') . "
        <p style='margin:14px 0 0;font-size:12.5px;color:#6b7280'>This link is valid for <strong>24 hours</strong>.</p>";

    $sent = sendMail($u['email'], $u['name'], '[4M Change] Setup Password — Resend', mailTemplate('Setup Your Account Password', $bodyHtml));

    if ($sent) {
        $success++;
    } else {
        $failed++;
    }
}

writeAuditLog($pdo, 'USER_UPDATED', "Resend all pending setup email — $success succeeded, $failed failed");

$msg = "Setup email sent successfully to $success user(s).";
if ($failed > 0) $msg .= " $failed failed.";

header('Location: index.php?success=email_resent&msg=' . urlencode($msg));
exit;