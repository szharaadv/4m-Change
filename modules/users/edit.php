<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
requireRole(['superadmin', 'admin']);
include '../../templates/header.php';
include '../../templates/navbar.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$u = $stmt->fetch();
if (!$u) die('User not found.');

$departments = [
    'Production (Assy, TR, PK)',
    'Painting',
    'MS1',
    'MS2',
    'CR',
    'QC Incoming',
    'QC Inhouse',
    'QC Product',
    'Picking Yard',
];
?>

<div class="page-header">
    <div>
        <div class="page-title">Edit User</div>
        <div class="page-sub">Perbarui data pengguna</div>
    </div>
    <a href="index.php" class="btn">Batal</a>
</div>

<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger"><?= e($_GET['error']) ?></div>
<?php endif; ?>

<form action="update.php" method="POST">
    <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
    <input type="hidden" name="id" value="<?= $u['id'] ?>">

    <div class="form-section">
        <div class="section-title"><span class="section-dot"></span>User Information</div>
        <div class="d-grid grid-2 gap-16">
            <div>
                <label class="form-label">Full Name <span class="required">*</span></label>
                <input type="text" name="name" class="form-control" required value="<?= e($u['name']) ?>">
            </div>
            <div>
                <label class="form-label">Username <span class="required">*</span></label>
                <input type="text" name="username" class="form-control" required value="<?= e($u['username']) ?>">
            </div>
            <div>
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= e($u['email'] ?? '') ?>"
                    placeholder="email@yanmar.co.id">
            </div>
            <div>
                <label class="form-label">Role <span class="required">*</span></label>
                <select name="role" class="form-control" required>
                    <?php foreach (['admin', 'manager', 'qc', 'qc_prod'] as $r): ?>
                    <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint" style="margin-top:4px">Role superadmin tidak dapat diubah lewat form ini.</div>
            </div>
            <div>
                <label class="form-label">Department</label>
                <select name="department" class="form-control">
                    <option value="">— Tidak ada —</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?= e($dept) ?>" <?= ($u['department'] ?? '') === $dept ? 'selected' : '' ?>>
                        <?= e($dept) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint" style="margin-top:4px">
                    Department tempat user bekerja — dipakai untuk routing approval.
                </div>
            </div>
            <div>
                <label class="form-label">Status</label>
                <select name="is_active" class="form-control">
                    <option value="1" <?= $u['is_active'] ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= !$u['is_active'] ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <div class="form-section">
        <div class="section-title"><span class="section-dot"></span>Change Password</div>
        <div class="form-hint" style="margin-bottom:12px">Kosongkan jika tidak ingin mengubah password.</div>
        <div class="d-grid grid-2 gap-16">
            <div>
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter...">
            </div>
            <div>
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="password_confirm" class="form-control" placeholder="Ulangi password baru...">
            </div>
        </div>
    </div>

    <div class="d-flex gap-8" style="justify-content:flex-end;padding-bottom:8px">
        <a href="index.php" class="btn">Batal</a>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
    </div>
</form>

<?php include '../../templates/footer.php'; ?>