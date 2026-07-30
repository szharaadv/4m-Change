<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
require_once '../../helpers/permissions.php';

// Only the superadmin may view or change the permission matrix.
requireRole([ROLE_SUPERADMIN]);

include '../../templates/header.php';
include '../../templates/navbar.php';

$map = loadRolePermissions($pdo);
?>

<div class="page-header">
    <div>
        <div class="page-title">Role Permissions</div>
        <div class="page-sub">Control what each role can access. The superadmin always has full access.</div>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">Role permissions saved successfully.</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger"><?= e($_GET['error']) ?></div>
<?php endif; ?>

<form action="save.php" method="POST">
    <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">

    <div class="table-wrap">
        <div class="card-header">Access Matrix</div>
        <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th style="min-width:240px">Permission</th>
                    <th style="width:120px;text-align:center">
                        <span class="badge badge-manager">superadmin</span>
                    </th>
                    <?php foreach (MANAGED_ROLES as $role): ?>
                    <th style="width:120px;text-align:center">
                        <span class="badge badge-submitted"><?= e($role) ?></span>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach (PERMISSIONS as $permKey => $permLabel): ?>
                <tr>
                    <td>
                        <div style="font-weight:500;font-size:13px"><?= e($permLabel) ?></div>
                        <div class="mono" style="font-size:11px;color:var(--muted)"><?= e($permKey) ?></div>
                    </td>
                    <td style="text-align:center">
                        <input type="checkbox" checked disabled title="Superadmin always has full access"
                               style="width:16px;height:16px">
                    </td>
                    <?php foreach (MANAGED_ROLES as $role): ?>
                    <?php $checked = in_array($permKey, $map[$role] ?? [], true); ?>
                    <td style="text-align:center">
                        <input type="checkbox"
                               name="perms[<?= e($role) ?>][]"
                               value="<?= e($permKey) ?>"
                               <?= $checked ? 'checked' : '' ?>
                               style="width:16px;height:16px;cursor:pointer">
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="d-flex gap-8" style="justify-content:flex-end;padding:16px 0 8px">
        <a href="<?= navLink('modules/dashboard/index.php') ?>" class="btn">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Permissions</button>
    </div>
</form>

<?php include '../../templates/footer.php'; ?>
