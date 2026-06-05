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
    header('Location: index.php?error=' . urlencode('Tidak ada user pending.'));
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

    $setupUrl = "http://" . $_SERVER['HTTP_HOST'] . "/4m-change/modules/auth/set_password.php?token=$token";

    $bodyHtml = "
        <p style='color:#444;font-size:13px;margin:0 0 16px'>Halo <strong>{$u['name']}</strong>,</p>
        <p style='color:#444;font-size:13px;margin:0 0 16px'>Ini adalah pengiriman ulang email setup password untuk akun kamu di sistem <strong>4M Change</strong>.</p>
        <table style='width:100%;border-collapse:collapse;font-size:13px;margin-bottom:16px'>
            <tr><td style='color:#888;padding:6px 0;width:120px'>Username</td><td style='padding:6px 0;font-weight:600;font-family:monospace'>{$u['username']}</td></tr>
            <tr><td style='color:#888;padding:6px 0'>Role</td><td style='padding:6px 0'>{$u['role']}</td></tr>
        </table>
        <div style='margin:20px 0'>
            <a href='$setupUrl' style='background:#D0021B;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600'>Set Password Saya</a>
        </div>
        <p style='color:#888;font-size:12px;margin:16px 0 0'>Link ini berlaku selama <strong>24 jam</strong>.</p>";

    $sent = sendMail($u['email'], $u['name'], '[4M Change] Setup Password — Pengiriman Ulang', mailTemplate('Setup Password Akun Anda', $bodyHtml));

    if ($sent) {
        $success++;
    } else {
        $failed++;
    }
}

writeAuditLog($pdo, 'USER_UPDATED', "Resend all pending setup email — $success berhasil, $failed gagal");

$msg = "Email setup berhasil dikirim ke $success user.";
if ($failed > 0) $msg .= " $failed gagal.";

header('Location: index.php?success=email_resent&msg=' . urlencode($msg));
exit;