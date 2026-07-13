<?php
require_once '../../config/database.php';

$token = trim($_GET['token'] ?? '');

if (!$token) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center">
        <h2>Invalid link.</h2>
        <p>Token not found.</p>
    </div>');
}

// Find user by token
$stmt = $pdo->prepare("SELECT * FROM users WHERE password_token = ? AND token_expires_at > NOW() AND is_active = 1 LIMIT 1");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center">
        <h2>Invalid or expired link.</h2>
        <p>Please request a new password reset.</p>
        <a href="forgot_password.php">Request Again</a>
    </div>');
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — 4M Change</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <div class="login-page">
        <div class="login-box">
            <div class="login-brand">
                <div class="login-mark">4M</div>
                <div class="login-title">Reset Password</div>
                <div class="login-sub">Hello, <strong><?= htmlspecialchars($user['name']) ?></strong>! Set your new
                    password.</div>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger" style="margin-bottom:16px"><?= htmlspecialchars($_GET['error']) ?></div>
            <?php endif; ?>

            <form action="process_reset_password.php" method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div style="margin-bottom:14px">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                </div>
                <div style="margin-bottom:14px">
                    <label class="form-label">New Password <span class="required">*</span></label>
                    <input type="password" name="password" class="form-control" required
                        placeholder="Minimum 6 characters..." autofocus>
                </div>
                <div style="margin-bottom:20px">
                    <label class="form-label">Confirm Password <span class="required">*</span></label>
                    <input type="password" name="password_confirm" class="form-control" required
                        placeholder="Repeat new password...">
                </div>

                <button type="submit" class="btn btn-primary w-100">Save New Password</button>
                <a href="login.php"
                    style="display:block;text-align:center;font-size:12px;color:var(--muted);margin-top:12px">← Back
                    to Login</a>
            </form>
        </div>
    </div>
</body>

</html>