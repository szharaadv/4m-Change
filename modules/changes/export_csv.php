<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
require_once '../../helpers/audit.php';
writeAuditLog($pdo, 'EXPORT_CSV', 'Export CSV change requests');
requireLogin();

$role = currentUserRole();
$userId = currentUserId();
$tab = $_GET['tab'] ?? 'all';

$where = [];
$params = [];

if (!empty($_GET['category_4m'])) {
    $where[] = 'cr.category_4m = ?';
    $params[] = $_GET['category_4m'];
}
if (!empty($_GET['part_name'])) {
    $where[] = 'cr.part_name LIKE ?';
    $params[] = '%' . $_GET['part_name'] . '%';
}
if (!empty($_GET['workflow_status'])) {
    $where[] = 'cr.workflow_status = ?';
    $params[] = $_GET['workflow_status'];
}

if ($tab === 'need_approval') {
    if (in_array($role, ['manager', 'admin'], true)) {
        $where[] = "cr.workflow_status = 'Submitted'";
    } elseif ($role === 'qc') {
        $where[] = "cr.workflow_status IN ('Manager Approved','QC Approved')";
    } else {
        $where[] = '1=0';
    }
}

$sql = "SELECT cr.*, u.name AS pic_name, c.name AS creator_name
        FROM change_requests cr
        JOIN users u ON cr.pic_id = u.id
        JOIN users c ON cr.created_by = c.id"
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY cr.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filename = '4m-change-export-' . date('Ymd-His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM for Excel UTF-8
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

fputcsv($out, [
    'Change No',
    'Category',
    'PIC',
    'Part No',
    'Part Name',
    'Model',
    'Implement Location',
    'Start Lot/Serial',
    '4M Change Item',
    'Action',
    'Before',
    'After',
    'Judge Status',
    'Confirm Customer',
    'Evidence Note',
    'Workflow Status',
    'Submitted By',
    'Created At',
    'Updated At',
]);

foreach ($rows as $row) {
    fputcsv($out, [
        $row['change_no'],
        $row['category_4m'],
        $row['pic_name'],
        $row['part_no'],
        $row['part_name'],
        $row['model'],
        $row['implement_location'],
        $row['start_lot_serial'],
        $row['change_item'],
        $row['action_plan'],
        $row['before_desc'],
        $row['after_desc'],
        $row['judge_status'],
        $row['confirm_customer'],
        $row['evidence_note'],
        $row['workflow_status'],
        $row['creator_name'],
        $row['created_at'],
        $row['updated_at'],
    ]);
}

fclose($out);
exit;
