<?php
require_once '../../config/database.php';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — 4M Change</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <div class="login-page">
        <div class="login-box">
            <div class="login-brand">
                <div class="login-mark">4M</div>
                <div class="login-title">Forgot Password</div>
                <div class="login-sub">Masukkan email kamu untuk mendapatkan link reset password.</div>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger" style="margin-bottom:16px">
                    <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success" style="margin-bottom:16px">
                    Link reset password telah dikirim ke email kamu. Cek inbox!
                </div>
            <?php endif; ?>

            <form action="process_forgot_password.php" method="POST">
                <div style="margin-bottom:14px">
                    <label class="form-label">Email <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control" required placeholder="email@yanmar.co.id"
                        autofocus>
                </div>
                <button type="submit" class="btn btn-primary w-100" style="margin-bottom:12px">Kirim Link Reset</button>
                <a href="login.php" style="display:block;text-align:center;font-size:12px;color:var(--muted)">← Kembali
                    ke Login</a>
            </form>
        </div>
    </div>
</body>

</html>