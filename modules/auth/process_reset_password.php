<?php
session_start();
require_once '../../config/database.php';
require_once '../../helpers/audit.php';

$email = trim($_POST['email'] ?? '');

if (!$email) {
    header('Location: forgot_password.php?error=' . urlencode('Email wajib diisi.'));
    exit;
}

// Cek user
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: forgot_password.php?error=' . urlencode('Email tidak terdaftar.'));
    exit;
}

// Generate token
$token = bin2hex(random_bytes(32));

// ✅ SET EXPIRED 1 JAM
$expires = date('Y-m-d H:i:s', time() + 3600);

// Simpan ke database
$pdo->prepare("UPDATE users 
    SET password_token = ?, token_expires_at = ? 
    WHERE id = ?")
->execute([$token, $expires, $user['id']]);

// Link reset
$resetLink = "http://yourdomain.com/modules/auth/reset_password.php?token=" . urlencode($token);

// ✅ (OPTIONAL) Kirim email — kalau belum ada, bisa kamu sambung nanti
/*
mail($email, 'Reset Password', 
    "Klik link berikut untuk reset password:\n\n$resetLink\n\nLink berlaku 1 jam."
);
*/

// Audit log
writeAuditLog(
    $pdo,
    'FORGOT_PASSWORD',
    "User {$user['username']} request reset password",
    $user['id'],
    $user['username'],
    $user['role']
);

// Redirect sukses
header('Location: forgot_password.php?success=' . urlencode('Link reset password sudah dikirim ke email.'));
exit;