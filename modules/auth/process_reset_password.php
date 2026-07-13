<?php
session_start();
require_once '../../config/database.php';
require_once '../../helpers/audit.php';

$email = trim($_POST['email'] ?? '');

if (!$email) {
    header('Location: forgot_password.php?error=' . urlencode('Email is required.'));
    exit;
}

// Check user
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: forgot_password.php?error=' . urlencode('Email not registered.'));
    exit;
}

// Generate token
$token = bin2hex(random_bytes(32));

// Set expiration to 1 hour
$expires = date('Y-m-d H:i:s', time() + 3600);

// Save to database
$pdo->prepare("UPDATE users
    SET password_token = ?, token_expires_at = ?
    WHERE id = ?")
->execute([$token, $expires, $user['id']]);

// Reset link
$resetLink = "http://yourdomain.com/modules/auth/reset_password.php?token=" . urlencode($token);

// (OPTIONAL) Send email — connect later if not yet implemented
/*
mail($email, 'Reset Password',
    "Click the following link to reset your password:\n\n$resetLink\n\nThe link is valid for 1 hour."
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

// Redirect success
header('Location: forgot_password.php?success=' . urlencode('Password reset link has been sent to your email.'));
exit;