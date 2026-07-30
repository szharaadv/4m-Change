<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
requirePermission('users.manage');
include '../../templates/header.php';
include '../../templates/navbar.php';
?>

<div class="page-header">
    <div>
        <div class="page-title">Add New User</div>
        <div class="page-sub">Add a new user account — the password will be set by the user</div>
    </div>
    <a href="index.php" class="btn">Cancel</a>
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
                <input type="text" name="name" class="form-control" required placeholder="Full name...">
            </div>
            <div>
                <label class="form-label">Username <span class="required">*</span></label>
                <input type="text" name="username" class="form-control" required placeholder="Unique username...">
            </div>
            <div>
                <label class="form-label">Email <span class="required">*</span></label>
                <input type="email" name="email" class="form-control" required placeholder="email@yanmar.co.id">
                <div class="form-hint">The password setup link will be sent to this email.</div>
            </div>
            <div>
                <label class="form-label">Role <span class="required">*</span></label>
                <select name="role" class="form-control" required>
                    <option value="">Select role...</option>
                    <option value="user">user</option>
                    <option value="admin">admin</option>
                </select>
                <div class="form-hint" style="margin-top:4px">Role access is configured by the superadmin under Roles.</div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-8" style="justify-content:flex-end;padding-bottom:8px">
        <a href="index.php" class="btn">Cancel</a>
        <button type="submit" class="btn btn-primary">Add & Send Setup Email</button>
    </div>
</form>

<?php include '../../templates/footer.php'; ?>