<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
requireRole(['superadmin']);
include '../../templates/header.php';
include '../../templates/navbar.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$u = $stmt->fetch();
if (!$u) die('User not found.');

// Get current assigned categories
$catStmt = $pdo->prepare("SELECT category_4m FROM category_managers WHERE user_id = ?");
$catStmt->execute([$id]);
$assignedCats = $catStmt->fetchAll(PDO::FETCH_COLUMN);

// Get current assigned departments
$deptStmt = $pdo->prepare("SELECT department FROM department_managers WHERE user_id = ?");
$deptStmt->execute([$id]);
$assignedDepts = $deptStmt->fetchAll(PDO::FETCH_COLUMN);

$departments = [
    'Production',
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
                <select name="role" class="form-control" required id="roleSelect" onchange="toggleSections()">
                    <?php foreach (['superadmin', 'admin', 'manager', 'qc'] as $r): ?>
                    <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Department</label>
                <select name="department" class="form-control">
                    <option value="">— Tidak ada —</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?= $dept ?>" <?= ($u['department'] ?? '') === $dept ? 'selected' : '' ?>><?= $dept ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint" style="margin-top:4px">Department tempat user bekerja.</div>
            </div>
        </div>
    </div>

    <!-- Category 4M Assignment — hanya untuk manager -->
    <div class="form-section" id="categorySection" style="<?= $u['role'] !== 'manager' ? 'display:none' : '' ?>">
        <div class="section-title"><span class="section-dot"></span>Category 4M Assignment</div>
        <div class="form-hint" style="margin-bottom:12px">Pilih kategori 4M yang ditangani manager ini.</div>
        <div class="d-flex gap-16" style="flex-wrap:wrap">
            <?php foreach (['Man', 'Material', 'Method', 'Machine'] as $cat): ?>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 16px;border:1.5px solid <?= in_array($cat, $assignedCats) ? 'var(--accent)' : 'var(--border)' ?>;border-radius:var(--radius);background:<?= in_array($cat, $assignedCats) ? 'var(--accent-light)' : '#fff' ?>">
                <input type="checkbox" name="categories[]" value="<?= $cat ?>"
                    <?= in_array($cat, $assignedCats) ? 'checked' : '' ?>
                    style="accent-color:var(--accent)">
                <span style="font-size:13px;font-weight:500;color:<?= in_array($cat, $assignedCats) ? 'var(--accent)' : 'var(--text)' ?>"><?= $cat ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Department Assignment — hanya untuk manager -->
    <div class="form-section" id="deptSection" style="<?= $u['role'] !== 'manager' ? 'display:none' : '' ?>">
        <div class="section-title"><span class="section-dot"></span>Department Assignment</div>
        <div class="form-hint" style="margin-bottom:12px">Pilih department yang ditangani manager ini untuk routing approval email.</div>
        <div class="d-flex gap-16" style="flex-wrap:wrap">
            <?php foreach ($departments as $dept): ?>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 16px;border:1.5px solid <?= in_array($dept, $assignedDepts) ? 'var(--accent)' : 'var(--border)' ?>;border-radius:var(--radius);background:<?= in_array($dept, $assignedDepts) ? 'var(--accent-light)' : '#fff' ?>">
                <input type="checkbox" name="departments[]" value="<?= $dept ?>"
                    <?= in_array($dept, $assignedDepts) ? 'checked' : '' ?>
                    style="accent-color:var(--accent)">
                <span style="font-size:13px;font-weight:500;color:<?= in_array($dept, $assignedDepts) ? 'var(--accent)' : 'var(--text)' ?>"><?= $dept ?></span>
            </label>
            <?php endforeach; ?>
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

<script>
function toggleSections() {
    const role = document.getElementById('roleSelect').value;
    const isManager = role === 'manager';
    document.getElementById('categorySection').style.display = isManager ? '' : 'none';
    document.getElementById('deptSection').style.display = isManager ? '' : 'none';
}
</script>

<?php include '../../templates/footer.php'; ?>