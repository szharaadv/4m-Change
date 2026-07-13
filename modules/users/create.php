<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
requireRole(['admin', 'superadmin']);
include '../../templates/header.php';
include '../../templates/navbar.php';
?>

<div class="page-header">
    <div>
        <div class="page-title">Add New User</div>
        <div class="page-sub">Tambah akun pengguna baru — password akan diatur oleh user</div>
    </div>
    <a href="index.php" class="btn">Batal</a>
</div>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?= e($_GET['error']) ?></div>
<?php endif; ?>

<form action="store.php" method="POST">
    <div class="form-section">
        <div class="section-title"><span class="section-dot"></span>User Information</div>
        <div class="d-grid grid-2 gap-16">
            <div>
                <label class="form-label">Full Name <span class="required">*</span></label>
                <input type="text" name="name" class="form-control" required placeholder="Nama lengkap...">
            </div>
            <div>
                <label class="form-label">Username <span class="required">*</span></label>
                <input type="text" name="username" class="form-control" required placeholder="Username unik...">
            </div>
            <div>
                <label class="form-label">Email <span class="required">*</span></label>
                <input type="email" name="email" class="form-control" required placeholder="email@yanmar.co.id">
                <div class="form-hint">Link setup password akan dikirim ke email ini.</div>
            </div>
            <div>
                <label class="form-label">Role <span class="required">*</span></label>
                <select name="role" class="form-control" required>
                    <option value="">Pilih role...</option>
                    <option value="qc_prod">qc_prod</option>
                    <option value="admin">admin</option>
                    <option value="manager">manager</option>
                    <option value="qc">qc</option>
                </select>
            </div>
        </div>
    </div>

    <div class="d-flex gap-8" style="justify-content:flex-end;padding-bottom:8px">
        <a href="index.php" class="btn">Batal</a>
        <button type="submit" class="btn btn-primary">Tambah & Kirim Email Setup</button>
    </div>
</form>

<?php include '../../templates/footer.php'; ?>