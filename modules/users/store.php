<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
require_once '../../helpers/mailer.php';
require_once '../../helpers/audit.php';
requireRole(['superadmin']);

$name = trim($_POST['name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = $_POST['role'] ?? '';

if (!$name || !$username || !$email || !$role) {
    header('Location: create.php?error=' . urlencode('All fields are required.'));
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: create.php?error=' . urlencode('Invalid email format.'));
    exit;
}

if (!in_array($role, ['admin', 'manager', 'qc'], true)) {
    header('Location: create.php?error=' . urlencode('Invalid role.'));
    exit;
}

$check = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$check->execute([$username]);
if ($check->fetch()) {
    header('Location: create.php?error=' . urlencode('Username is already in use.'));
    exit;
}

$checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$checkEmail->execute([$email]);
if ($checkEmail->fetch()) {
    header('Location: create.php?error=' . urlencode('Email is already in use.'));
    exit;
}

$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

try {
    $pdo->prepare("INSERT INTO users (name, username, email, password, role, password_token, token_expires_at, is_active) VALUES (?, ?, ?, '', ?, ?, ?, 0)")
        ->execute([$name, $username, $email, $role, $token, $expiresAt]);

    writeAuditLog($pdo, 'USER_CREATED', "Added new user: $username ($role) — $email");

    $setupUrl = APP_URL . "/modules/auth/set_password.php?token=$token";
    
    $bodyHtml = mailGreeting($name) . "
        <p style='margin:0 0 14px'>Your account on the <strong>4M Change</strong> system has been created by the Administrator. Click the button below to set your password.</p>
        " . mailInfoTable(['Username' => "<span style='font-family:monospace'>$username</span>", 'Role' => $role]) . "
        " . mailButton($setupUrl, 'Set My Password') . "
        <p style='margin:14px 0 0;font-size:12.5px;color:#6b7280'>This link is valid for <strong>24 hours</strong>. If you did not expect this, please ignore this email.</p>";

    sendMail($email, $name, '[4M Change] New Account — Setup Password', mailTemplate('Setup Your Account Password', $bodyHtml));

    header('Location: index.php?success=created');
    exit;
} catch (Exception $e) {
    header('Location: create.php?error=' . urlencode($e->getMessage()));
    exit;
}