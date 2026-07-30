<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
require_once '../../helpers/audit.php';
requirePermission('audit.view');
include '../../templates/header.php';
include '../../templates/navbar.php';

// Filters
$filterAction = $_GET['action'] ?? '';
$filterUser = $_GET['user'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Build query
$where = [];
$params = [];

if ($filterAction) {
    $where[] = 'action LIKE ?';
    $params[] = $filterAction . '%';
}
if ($filterUser) {
    $where[] = '(username LIKE ? OR role LIKE ?)';
    $params[] = '%' . $filterUser . '%';
    $params[] = '%' . $filterUser . '%';
}
if ($filterDateFrom) {
    $where[] = 'DATE(created_at) >= ?';
    $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $where[] = 'DATE(created_at) <= ?';
    $params[] = $filterDateTo;
}

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs $whereStr");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPage = (int) ceil($total / $perPage);

// Fetch logs
$stmt = $pdo->prepare("SELECT * FROM audit_logs $whereStr ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Action options for filter
$actionGroups = [
    'AUTH' => ['LOGIN', 'LOGIN_FAILED', 'LOGOUT'],
    'USER' => ['USER_CREATED', 'USER_UPDATED', 'USER_DELETED'],
    'CHANGE' => ['CHANGE_CREATED', 'CHANGE_SUBMITTED', 'CHANGE_APPROVED', 'CHANGE_REJECTED', 'CHANGE_CLOSED'],
    'PASSWORD' => ['PASSWORD_SET', 'PASSWORD_RESET'],
    'EXPORT' => ['EXPORT_PDF', 'EXPORT_CSV'],
];
?>

<div class="page-header">
    <div>
        <div class="page-title">Audit Log</div>
        <div class="page-sub">Record of all system user activity</div>
    </div>
    <div style="font-size:12px;color:var(--muted)">Total: <strong>
            <?= number_format($total) ?>
        </strong> log</div>
</div>

<!-- Filter -->
<div class="filter-bar mb-3">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div>
            <label class="form-label">Action</label>
            <select name="action" class="form-control" style="min-width:160px">
                <option value="">All Actions</option>
                <?php foreach ($actionGroups as $group => $actions): ?>
                    <optgroup label="<?= $group ?>">
                        <?php foreach ($actions as $act): ?>
                            <option value="<?= $act ?>" <?= $filterAction === $act ? 'selected' : '' ?>>
                                <?= $act ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label">User / Role</label>
            <input type="text" name="user" class="form-control" value="<?= e($filterUser) ?>"
                placeholder="Search username/role...">
        </div>
        <div>
            <label class="form-label">From Date</label>
            <input type="date" name="date_from" class="form-control" value="<?= e($filterDateFrom) ?>">
        </div>
        <div>
            <label class="form-label">To Date</label>
            <input type="date" name="date_to" class="form-control" value="<?= e($filterDateTo) ?>">
        </div>
        <div style="display:flex;gap:6px">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="index.php" class="btn">Reset</a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="table-wrap">
    <div class="card-header">
        Audit Log
        <span style="color:var(--muted);font-weight:400;font-size:11px">
            Page
            <?= $page ?> of
            <?= $totalPage ?> ·
            <?= number_format($total) ?> total
        </span>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>Time</th>
                <th>User</th>
                <th>Role</th>
                <th>Action</th>
                <th>Description</th>
                <th>IP Address</th>
                <th>PC Name</th>
                <th>Browser</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$logs): ?>
                <tr>
                    <td colspan="8" style="text-align:center;color:var(--muted);padding:24px">No logs found.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td style="white-space:nowrap;font-size:11px;color:var(--muted)">
                        <?= date('d M Y', strtotime($log['created_at'])) ?><br>
                        <span style="font-family:var(--mono)">
                            <?= date('H:i:s', strtotime($log['created_at'])) ?>
                        </span>
                    </td>
                    <td>
                        <div style="font-weight:500;font-size:12px">
                            <?= e($log['username'] ?? '—') ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($log['role']): ?>
                            <span class="badge"
                                style="background:<?= getActionBadgeColor($log['action']) ?>20;color:<?= getActionBadgeColor($log['action']) ?>">
                                <?= e($log['role']) ?>
                            </span>
                        <?php else: ?>
                            <span style="color:var(--muted);font-size:11px">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge"
                            style="background:<?= getActionBadgeColor($log['action']) ?>20;color:<?= getActionBadgeColor($log['action']) ?>;font-size:10px">
                            <?= e($log['action']) ?>
                        </span>
                    </td>
                    <td style="font-size:12px;max-width:250px">
                        <?= e($log['description'] ?? '—') ?>
                    </td>
                    <td style="font-family:var(--mono);font-size:11px;color:var(--muted)">
                        <?= e($log['ip_address'] ?? '—') ?>
                    </td>
                    <td style="font-size:11px;color:var(--muted);max-width:150px">
                        <?= e($log['pc_name'] ?? '—') ?>
                    </td>
                    <td style="font-size:11px;color:var(--muted)">
                        <?= $log['user_agent'] ? e(getClientBrowser($log['user_agent'])) : '—' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalPage > 1): ?>
        <div style="display:flex;justify-content:center;gap:6px;padding:14px">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-sm">← Prev</a>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end = min($totalPage, $page + 2);
            for ($i = $start; $i <= $end; $i++):
                ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                    class="btn btn-sm <?= $i === $page ? 'btn-primary' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPage): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-sm">Next →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../../templates/footer.php'; ?>