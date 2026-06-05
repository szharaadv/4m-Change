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

<?php if (!$managers): ?>
<div class="alert alert-warning">Belum ada user dengan role <strong>manager</strong>. Tambahkan dulu di halaman Users.</div>
<?php endif; ?>

<?php if (!$qcUsers): ?>
<div class="alert alert-warning">Belum ada user dengan role <strong>qc</strong>. Tambahkan dulu di halaman Users.</div>
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
                    <td style="font-weight:600;font-size:13px;vertical-align:top;padding-top:14px">
                        <?= e($dept) ?>
                    </td>
                    <td style="vertical-align:top;padding-top:12px">
                        <?php if ($managers): ?>
                        <div class="d-flex gap-12" style="flex-wrap:wrap">
                            <?php foreach ($managers as $mgr): ?>
                            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;
                                padding:6px 12px;border:1.5px solid <?= in_array($mgr['id'], $deptMgrs[$dept] ?? []) ? 'var(--accent)' : 'var(--border)' ?>;
                                border-radius:var(--radius);
                                background:<?= in_array($mgr['id'], $deptMgrs[$dept] ?? []) ? 'var(--accent-light)' : '#fff' ?>">
                                <input type="checkbox"
                                    name="routing[<?= e($dept) ?>][manager][]"
                                    value="<?= $mgr['id'] ?>"
                                    <?= in_array($mgr['id'], $deptMgrs[$dept] ?? []) ? 'checked' : '' ?>
                                    style="accent-color:var(--accent)">
                                <span style="font-weight:500;color:<?= in_array($mgr['id'], $deptMgrs[$dept] ?? []) ? 'var(--accent)' : 'var(--text)' ?>">
                                    <?= e($mgr['name']) ?>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <span style="color:var(--muted);font-size:12px">— Belum ada manager</span>
                        <?php endif; ?>
                    </td>
                    <td style="vertical-align:top;padding-top:12px">
                        <?php if ($qcUsers): ?>
                        <div class="d-flex gap-12" style="flex-wrap:wrap">
                            <?php foreach ($qcUsers as $qc): ?>
                            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;
                                padding:6px 12px;border:1.5px solid <?= in_array($qc['id'], $deptQcs[$dept] ?? []) ? 'var(--accent)' : 'var(--border)' ?>;
                                border-radius:var(--radius);
                                background:<?= in_array($qc['id'], $deptQcs[$dept] ?? []) ? 'var(--accent-light)' : '#fff' ?>">
                                <input type="checkbox"
                                    name="routing[<?= e($dept) ?>][qc][]"
                                    value="<?= $qc['id'] ?>"
                                    <?= in_array($qc['id'], $deptQcs[$dept] ?? []) ? 'checked' : '' ?>
                                    style="accent-color:var(--accent)">
                                <span style="font-weight:500;color:<?= in_array($qc['id'], $deptQcs[$dept] ?? []) ? 'var(--accent)' : 'var(--text)' ?>">
                                    <?= e($qc['name']) ?>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <span style="color:var(--muted);font-size:12px">— Belum ada QC</span>
                        <?php endif; ?>
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