<?php
require_once '../../config/database.php';

$token = trim($_GET['token'] ?? '');

if (!$token) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center">
        <h2>Link tidak valid.</h2>
        <p>Token tidak ditemukan.</p>
    </div>');
}

// Find user by token
$stmt = $pdo->prepare("SELECT * FROM users WHERE password_token = ? AND token_expires_at > NOW() AND is_active = 1 LIMIT 1");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center">
        <h2>Link tidak valid atau sudah kadaluarsa.</h2>
        <p>Silakan request reset password lagi.</p>
        <a href="forgot_password.php">Request Ulang</a>
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
                <div class="login-sub">Halo, <strong><?= htmlspecialchars($user['name']) ?></strong>! Atur password baru
                    kamu.</div>
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
                    <label class="form-label">Password Baru <span class="required">*</span></label>
                    <input type="password" name="password" class="form-control" required
                        placeholder="Minimal 6 karakter..." autofocus>
                </div>
                <div style="margin-bottom:20px">
                    <label class="form-label">Konfirmasi Password <span class="required">*</span></label>
                    <input type="password" name="password_confirm" class="form-control" required
                        placeholder="Ulangi password baru...">
                </div>

                <button type="submit" class="btn btn-primary w-100">Simpan Password Baru</button>
                <a href="login.php"
                    style="display:block;text-align:center;font-size:12px;color:var(--muted);margin-top:12px">← Kembali
                    ke Login</a>
            </form>
        </div>
    </div>
</body>

</html>