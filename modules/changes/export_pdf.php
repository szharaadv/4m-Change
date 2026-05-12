<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT cr.*, u.name AS pic_name, c.name AS creator_name
    FROM change_requests cr
    JOIN users u ON cr.pic_id = u.id
    JOIN users c ON cr.created_by = c.id
    WHERE cr.id = ? LIMIT 1
");
$stmt->execute([$id]);
$data = $stmt->fetch();
if (!$data)
    die('Data not found.');

$photos = $pdo->prepare("SELECT * FROM change_photos WHERE change_request_id = ? ORDER BY id");
$photos->execute([$id]);
$photos = $photos->fetchAll();

$approvalLogs = $pdo->prepare("
    SELECT ca.*, u.name AS approver_name FROM change_approvals ca
    JOIN users u ON ca.approver_id = u.id
    WHERE ca.change_request_id = ? ORDER BY ca.acted_at ASC
");
$approvalLogs->execute([$id]);
$approvalLogs = $approvalLogs->fetchAll();

$beforePhoto = $afterPhoto = null;
foreach ($photos as $p) {
    if ($p['photo_type'] === 'before')
        $beforePhoto = $p;
    if ($p['photo_type'] === 'after')
        $afterPhoto = $p;
}

$mgrApproval = $qcApproval = $qcFinal = null;
foreach ($approvalLogs as $log) {
    if ($log['step'] === 'manager')
        $mgrApproval = $log;
    if ($log['step'] === 'qc')
        $qcApproval = $log;
    if ($log['step'] === 'qc_final')
        $qcFinal = $log;
}

// Load mPDF
require_once '../../vendor/autoload.php';
date_default_timezone_set('Asia/Jakarta');

$mpdf = new \Mpdf\Mpdf([
    'margin_top' => 15,
    'margin_bottom' => 15,
    'margin_left' => 15,
    'margin_right' => 15,
    'format' => 'A4',
]);

// Convert photos to base64 for PDF embedding
function imgToBase64(string $path): string
{
    $fullPath = realpath(__DIR__ . '/../../' . $path);
    if ($fullPath && file_exists($fullPath)) {
        $type = pathinfo($fullPath, PATHINFO_EXTENSION);
        $data = base64_encode(file_get_contents($fullPath));
        return "data:image/$type;base64,$data";
    }
    return '';
}

$beforeImg = $beforePhoto ? imgToBase64($beforePhoto['file_path']) : '';
$afterImg = $afterPhoto ? imgToBase64($afterPhoto['file_path']) : '';

// Approval rows
function approvalRow(string $label, ?array $appr): string
{
    if (!$appr)
        return "
        <tr>
            <td style='padding:6px 10px;color:#888;width:160px'>$label</td>
            <td style='padding:6px 10px;color:#aaa'>Menunggu</td>
            <td style='padding:6px 10px;color:#aaa'>—</td>
            <td style='padding:6px 10px;color:#aaa'>—</td>
        </tr>";

    $status = $appr['status'] === 'Approved'
        ? "<span style='color:#16a34a;font-weight:600'>✓ Disetujui</span>"
        : "<span style='color:#dc2626;font-weight:600'>✗ Ditolak</span>";
    $date = date('d M Y H:i', strtotime($appr['acted_at']));

    return "
        <tr>
            <td style='padding:6px 10px;color:#555;width:160px'>$label</td>
            <td style='padding:6px 10px'>{$appr['approver_name']}</td>
            <td style='padding:6px 10px'>$status</td>
            <td style='padding:6px 10px;color:#888;font-size:11px'>$date</td>
        </tr>";
}

$html = "
<html>
<head>
<style>
    body { font-family: Arial, sans-serif; font-size: 12px; color: #1a1a1a; }
    .header { background: #1a1a1a; color: #fff; padding: 16px 20px; margin-bottom: 20px; }
    .header-title { font-size: 18px; font-weight: bold; }
    .header-sub { font-size: 11px; color: rgba(255,255,255,0.6); margin-top: 4px; }
    .badge-red { background: #D0021B; color: #fff; padding: 3px 10px; border-radius: 4px; font-size: 11px; }
    .section { margin-bottom: 16px; }
    .section-title { font-size: 12px; font-weight: bold; color: #D0021B; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e5e5e5; padding-bottom: 6px; margin-bottom: 10px; }
    table.info { width: 100%; border-collapse: collapse; }
    table.info td { padding: 5px 8px; font-size: 12px; border-bottom: 1px solid #f5f5f5; }
    table.info td.key { color: #888; width: 140px; }
    table.approval { width: 100%; border-collapse: collapse; font-size: 12px; }
    table.approval th { background: #f5f5f5; padding: 7px 10px; text-align: left; font-size: 11px; color: #666; }
    table.approval tr:nth-child(even) { background: #fafafa; }
    .photo-wrap { display: inline-block; width: 48%; vertical-align: top; }
    .photo-wrap img { width: 100%; max-height: 200px; object-fit: cover; border: 1px solid #e5e5e5; border-radius: 4px; }
    .photo-label { font-size: 10px; font-weight: bold; color: #888; text-transform: uppercase; margin-bottom: 4px; }
    .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #e5e5e5; font-size: 10px; color: #aaa; text-align: center; }
</style>
</head>
<body>

<!-- Header -->
<div class='header'>
    <div style='display:table;width:100%'>
        <div style='display:table-cell;vertical-align:middle'>
            <div class='header-title'>4M Change — " . htmlspecialchars($data['change_no']) . "</div>
            <div class='header-sub'>Dicetak pada " . date('d M Y H:i') . " Oleh <b>" . htmlspecialchars(currentUser()['name']) . "</b> · Quality Management System</div>
        </div>
        <div style='display:table-cell;text-align:right;vertical-align:middle'>
            <span class='badge-red'>" . htmlspecialchars($data['workflow_status']) . "</span>
        </div>
    </div>
</div>

<!-- Informasi Umum -->
<div class='section'>
    <div class='section-title'>Informasi Umum</div>
    <table class='info'>
        <tr><td class='key'>Change No</td><td><b>" . htmlspecialchars($data['change_no']) . "</b></td><td class='key'>Location</td><td>" . htmlspecialchars($data['implement_location'] ?: '—') . "</td></tr>
        <tr><td class='key'>Kategori 4M</td><td>" . htmlspecialchars($data['category_4m']) . "</td><td class='key'>Start Lot</td><td>" . htmlspecialchars($data['start_lot_serial'] ?: '—') . "</td></tr>
        <tr><td class='key'>Part Name</td><td>" . htmlspecialchars($data['part_name']) . "</td><td class='key'>Confirm Customer</td><td>" . htmlspecialchars($data['confirm_customer']) . "</td></tr>
        <tr><td class='key'>Part No</td><td>" . htmlspecialchars($data['part_no'] ?: '—') . "</td><td class='key'>Judge Status</td><td>" . htmlspecialchars($data['judge_status']) . "</td></tr>
        <tr><td class='key'>PIC</td><td>" . htmlspecialchars($data['pic_name']) . "</td><td class='key'>Dibuat oleh</td><td>" . htmlspecialchars($data['creator_name']) . "</td></tr>
        <tr><td class='key'>Model</td><td>" . htmlspecialchars($data['model'] ?: '—') . "</td><td class='key'>Dibuat pada</td><td>" . date('d M Y H:i', strtotime($data['created_at'])) . "</td></tr>
    </table>
</div>

<!-- Detail Perubahan -->
<div class='section'>
    <div class='section-title'>Detail Perubahan</div>
    <table class='info'>
        <tr><td class='key' style='vertical-align:top'>4M Change Item</td><td>" . nl2br(htmlspecialchars($data['change_item'] ?: '—')) . "</td><td class='key' style='vertical-align:top'>Action</td><td>" . nl2br(htmlspecialchars($data['action_plan'] ?: '—')) . "</td></tr>
        <tr><td class='key' style='vertical-align:top'>Before</td><td>" . nl2br(htmlspecialchars($data['before_desc'] ?: '—')) . "</td><td class='key' style='vertical-align:top'>After</td><td>" . nl2br(htmlspecialchars($data['after_desc'] ?: '—')) . "</td></tr>
        " . ($data['evidence_note'] ? "<tr><td class='key' style='vertical-align:top'>Evidence Note</td><td colspan='3'>" . nl2br(htmlspecialchars($data['evidence_note'])) . "</td></tr>" : "") . "
    </table>
</div>

<!-- Status Approval -->
<div class='section'>
    <div class='section-title'>Status Approval</div>
    <table class='approval'>
        <thead>
            <tr>
                <th>Tahap</th>
                <th>Approver</th>
                <th>Status</th>
                <th>Tanggal</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style='padding:6px 10px;color:#555'>Submitter</td>
                <td style='padding:6px 10px'>" . htmlspecialchars($data['creator_name']) . "</td>
                <td style='padding:6px 10px;color:#16a34a;font-weight:600'>✓ Submitted</td>
                <td style='padding:6px 10px;color:#888;font-size:11px'>" . date('d M Y H:i', strtotime($data['created_at'])) . "</td>
            </tr>
            " . approvalRow('Manager Department', $mgrApproval) . "
            " . approvalRow('QC', $qcApproval) . "
            " . approvalRow('QC — Final Submit', $qcFinal) . "
        </tbody>
    </table>
</div>

" . ($beforeImg || $afterImg ? "
<div class='section'>
    <div class='section-title'>Evidence Photos</div>
    <table style='width:100%;border-collapse:collapse'>
        <tr>
            " . ($beforeImg ? "
            <td style='width:50%;padding-right:8px;vertical-align:top'>
                <div class='photo-label' style='margin-bottom:6px'>Before</div>
                <img src='$beforeImg' style='width:50%;height:50%;object-fit:cover;border:1px solid #e5e5e5;border-radius:4px'>
            </td>" : "<td style='width:50%'></td>") . "
            " . ($afterImg ? "
            <td style='width:50%;padding-left:8px;vertical-align:top'>
                <div class='photo-label' style='margin-bottom:6px'>After</div>
                <img src='$afterImg' style='width:50%;height:50%;object-fit:cover;border:1px solid #e5e5e5;border-radius:4px'>
            </td>" : "<td style='width:50%'></td>") . "
        </tr>
    </table>
</div>
" : "") . "
</body>
</html>";

$mpdf->WriteHTML($html);
require_once '../../helpers/audit.php';
writeAuditLog($pdo, 'EXPORT_PDF', "Export PDF — {$data['change_no']}");
$mpdf->Output('4M-Change-' . $data['change_no'] . '.pdf', 'I');