<?php
session_start();
require_once '../../config/database.php';
require_once '../../helpers/auth.php';

if (isLoggedIn()) {
    header('Location: /4m-change/modules/dashboard/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — 4M Change</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap"
        rel="stylesheet">
    <link href="/4m-change/assets/css/style.css" rel="stylesheet">
</head>

<body>
    <div class="login-page">
        <div class="login-box">
            <div class="login-brand">
                <div class="login-mark">4M</div>
                <div class="login-title">4M Change</div>
                <div class="login-sub">Quality Management System</div>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger mb-16">Username atau password salah.</div>
            <?php endif; ?>

            <form action="process_login.php" method="POST">
                <div class="mb-12">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Masukkan username" required
                        autofocus>
                </div>
                <div class="mb-12">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <div style="text-align:right;margin-bottom:20px">
                    <a href="forgot_password.php" style="font-size:12px;color:var(--accent);text-decoration:none">
                        Forgot Password?
                    </a>
                </div>
                <button type="submit" class="btn btn-primary btn-w100" style="padding:10px">Login</button>
            </form>
        </div>
    </div>
</body>

</html>