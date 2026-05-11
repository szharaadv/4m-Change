<?php
session_start();
require_once '../../config/database.php';

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Support plain text password (dev) and hashed password (production)
$valid = $user && (
    $password === $user['password'] ||
    (strlen($user['password']) > 30 && password_verify($password, $user['password']))
);

if ($valid) {
    $_SESSION['user'] = [
        'id'       => $user['id'],
        'name'     => $user['name'],
        'username' => $user['username'],
        'role'     => $user['role'],
    ];
    header('Location: /4m-change/modules/dashboard/index.php');
    exit;
}

header('Location: login.php?error=1');
exit;
