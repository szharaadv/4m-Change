<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
requireLogin();
include '../../templates/header.php';
include '../../templates/navbar.php';

$role   = currentUserRole();
$userId = currentUserId();
$tab    = $_GET['tab'] ?? 'all';

$where  = [];
$params = [];

// Filters
if (!empty($_GET['category_4m'])) { $where[] = 'cr.category_4m = ?'; $params[] = $_GET['category_4m']; }
if (!empty($_GET['part_name']))    { $where[] = 'cr.part_name LIKE ?'; $params[] = '%' . $_GET['part_name'] . '%'; }
if (!empty($_GET['workflow_status'])) { $where[] = 'cr.workflow_status = ?'; $params[] = $_GET['workflow_status']; }

// Tab filters
if ($tab === 'need_approval') {
    if (in_array($role, ['manager','admin'], true)) { $where[] = "cr.workflow_status = 'Submitted'"; }
    elseif ($role === 'qc') { $where[] = "cr.workflow_status IN ('Manager Approved','QC Approved')"; }
    else { $where[] = '1=0'; }
}

$joinApproval = '';
if ($tab === 'my_done') {
    $joinApproval = 'JOIN change_approvals ca ON ca.change_request_id = cr.id';
    $where[]  = 'ca.approver_id = ?';
    $params[] = $userId;
}

$sql = "SELECT DISTINCT cr.*, u.name AS pic_name
        FROM change_requests cr
        JOIN users u ON cr.pic_id = u.id
        {$joinApproval}"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY cr.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$needCount    = getNeedCount($pdo, $role, $userId);
$totalChange  = (int)$pdo->query("SELECT COUNT(*) FROM change_requests")->fetchColumn();
$closedMonth  = (int)$pdo->query("SELECT COUNT(*) FROM change_requests WHERE workflow_status='Closed' AND MONTH(updated_at)=MONTH(NOW()) AND YEAR(updated_at)=YEAR(NOW())")->fetchColumn();
$needCustomer = (int)$pdo->query("SELECT COUNT(*) FROM change_requests WHERE confirm_customer='Need'")->fetchColumn();

$statusMap = ['Draft'=>'draft','Submitted'=>'submitted','Manager Approved'=>'manager','QC Approved'=>'qc','Closed'=>'closed','Rejected'=>'rejected'];
?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible">Data berhasil disimpan.</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <div class="page-title">4M Change Management Record</div>
        <div class="page-sub">History</div>
    </div>
    <div class="d-flex gap-8">
        <a href="export_csv.php?<?= http_build_query(array_merge($_GET, ['tab' => $tab])) ?>" class="btn">Export CSV</a>
        <?php if (in_array($role, ['admin','manager','qc','qc_prod'], true)): ?>
        <a href="create.php" class="btn btn-primary">+ New Change</a>
        <?php endif; ?>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-label">Total Change</div><div class="stat-val"><?= $totalChange ?></div></div>
    <div class="stat-card"><div class="stat-label">Menunggu Approval Saya</div><div class="stat-val <?= $needCount > 0 ? 'text-warning' : '' ?>"><?= $needCount ?></div></div>
    <div class="stat-card"><div class="stat-label">Closed Bulan Ini</div><div class="stat-val text-success"><?= $closedMonth ?></div></div>
    <div class="stat-card"><div class="stat-label">Need Customer</div><div class="stat-val"><?= $needCustomer ?></div></div>
</div>

<?php if ($needCount > 0): ?>
<div class="alert alert-warning">
    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" style="flex-shrink:0"><path d="M8 15A7 7 0 118 1a7 7 0 010 14zm0 1A8 8 0 108 0a8 8 0 000 16z"/><path d="M7.002 11a1 1 0 112 0 1 1 0 01-2 0zM7.1 4.995a.905.905 0 111.8 0l-.35 3.507a.552.552 0 01-1.1 0L7.1 4.995z"/></svg>
    Ada <strong><?= $needCount ?></strong> permohonan yang menunggu approval Anda.
</div>
<?php endif; ?>

<div class="tab-bar">
    <a class="tab-item <?= $tab === 'all' ? 'active' : '' ?>" href="?tab=all">Semua</a>
    <?php if (in_array($role, ['manager','admin','qc'], true)): ?>
    <a class="tab-item <?= $tab === 'need_approval' ? 'active' : '' ?>" href="?tab=need_approval">
        Perlu Approval Saya
        <?php if ($needCount > 0): ?><span class="nav-badge"><?= $needCount ?></span><?php endif; ?>
    </a>
    <a class="tab-item <?= $tab === 'my_done' ? 'active' : '' ?>" href="?tab=my_done">Sudah Saya Proses</a>
    <?php endif; ?>
</div>

<!-- Filter -->
<div class="filter-bar mb-16">
    <form method="GET" class="d-flex gap-12 al-center" style="flex-wrap:wrap">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div>
            <label class="form-label">Kategori</label>
            <select name="category_4m" class="form-control" style="min-width:120px">
                <option value="">Semua</option>
                <?php foreach (['Man','Material','Method','Machine'] as $cat): ?>
                <option <?= (($_GET['category_4m'] ?? '') === $cat) ? 'selected' : '' ?>><?= $cat ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label">Part Name</label>
            <input type="text" name="part_name" class="form-control" value="<?= e($_GET['part_name'] ?? '') ?>" placeholder="Cari...">
        </div>
        <div>
            <label class="form-label">Status</label>
            <select name="workflow_status" class="form-control" style="min-width:140px">
                <option value="">Semua</option>
                <?php foreach (['Draft','Submitted','Manager Approved','QC Approved','Closed','Rejected'] as $st): ?>
                <option <?= (($_GET['workflow_status'] ?? '') === $st) ? 'selected' : '' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="padding-top:16px;display:flex;gap:8px">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="?tab=<?= e($tab) ?>" class="btn btn-sm">Reset</a>
        </div>
    </form>
</div>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th style="width:36px">No</th>
                <th style="width:120px">Change No</th>
                <th style="width:80px">Kategori</th>
                <th style="width:100px">PIC</th>
                <th>Part Name</th>
                <th style="width:70px">Judge</th>
                <th style="width:85px">Customer</th>
                <th style="width:130px">Status</th>
                <th style="width:90px">Tanggal</th>
                <th style="width:80px">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows): ?>
            <tr><td colspan="10" style="text-align:center;color:var(--muted);padding:24px">Tidak ada data.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $i => $row): ?>
            <?php $needsMyApproval = canApprove($role, $row['workflow_status']); ?>
            <tr class="clickable <?= $needsMyApproval ? 'needs-action' : '' ?>" onclick="location.href='detail.php?id=<?= $row['id'] ?>'">
                <td><?= $i + 1 ?></td>
                <td class="mono"><?= e($row['change_no']) ?></td>
                <td><?= e($row['category_4m']) ?></td>
                <td><?= e($row['pic_name']) ?></td>
                <td class="fw-600"><?= e($row['part_name']) ?></td>
                <td><span class="badge badge-<?= strtolower($row['judge_status']) ?>"><?= e($row['judge_status']) ?></span></td>
                <td style="font-size:12px"><?= e($row['confirm_customer']) ?></td>
                <td><span class="badge badge-<?= $statusMap[$row['workflow_status']] ?? 'draft' ?>"><?= e($row['workflow_status']) ?></span></td>
                <td class="text-muted" style="font-size:12px"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                <td onclick="event.stopPropagation()">
                    <a href="detail.php?id=<?= $row['id'] ?>" class="btn btn-sm">Detail</a>
                    <?php if (in_array($role, ['admin','qc_prod'], true) && $row['workflow_status'] === 'Rejected'): ?>
                    <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm" style="margin-top:4px;display:block">Edit</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../../templates/footer.php'; ?>
