<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
requireRole(['superadmin', 'admin']);
include '../../templates/header.php';
include '../../templates/navbar.php';

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

$managers = $pdo->query("SELECT id, name FROM users WHERE role = 'manager' AND is_active = 1 ORDER BY name ASC")->fetchAll();
$qcUsers  = $pdo->query("SELECT id, name FROM users WHERE role = 'qc' AND is_active = 1 ORDER BY name ASC")->fetchAll();

// Load existing routing
$deptMgrs = [];
foreach ($pdo->query("SELECT department, manager_id FROM department_managers")->fetchAll() as $row) {
    $deptMgrs[$row['department']][] = (int)$row['manager_id'];
}

$deptQcs = [];
foreach ($pdo->query("SELECT department, qc_id FROM department_qc")->fetchAll() as $row) {
    $deptQcs[$row['department']][] = (int)$row['qc_id'];
}
?>

<div class="page-header">
    <div>
        <div class="page-title">Routing Management</div>
        <div class="page-sub">Atur alur approval per department</div>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">Routing berhasil disimpan.</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger"><?= e($_GET['error']) ?></div>
<?php endif; ?>

<form action="save.php" method="POST">
    <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">

    <div class="table-wrap">
        <div class="card-header">Routing per Department</div>
        <table class="table">
            <thead>
                <tr>
                    <th style="width:200px">Department</th>
                    <th>Manager yang Approve</th>
                    <th>QC yang Approve</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($departments as $dept): ?>
                <tr>
                    <td style="font-weight:600;font-size:13px"><?= e($dept) ?></td>
                    <td>
                        <div class="d-flex gap-12" style="flex-wrap:wrap">
                            <?php foreach ($managers as $mgr): ?>
                            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                                <input type="checkbox"
                                    name="routing[<?= e($dept) ?>][manager][]"
                                    value="<?= $mgr['id'] ?>"
                                    <?= in_array($mgr['id'], $deptMgrs[$dept] ?? []) ? 'checked' : '' ?>
                                    style="accent-color:var(--accent)">
                                <?= e($mgr['name']) ?>
                            </label>
                            <?php endforeach; ?>
                            <?php if (!$managers): ?>
                            <span style="color:var(--muted);font-size:12px">Belum ada user manager</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex gap-12" style="flex-wrap:wrap">
                            <?php foreach ($qcUsers as $qc): ?>
                            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                                <input type="checkbox"
                                    name="routing[<?= e($dept) ?>][qc][]"
                                    value="<?= $qc['id'] ?>"
                                    <?= in_array($qc['id'], $deptQcs[$dept] ?? []) ? 'checked' : '' ?>
                                    style="accent-color:var(--accent)">
                                <?= e($qc['name']) ?>
                            </label>
                            <?php endforeach; ?>
                            <?php if (!$qcUsers): ?>
                            <span style="color:var(--muted);font-size:12px">Belum ada user QC</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="d-flex gap-8" style="justify-content:flex-end;padding:16px 0">
        <button type="submit" class="btn btn-primary">Simpan Routing</button>
    </div>
</form>

<?php include '../../templates/footer.php'; ?>