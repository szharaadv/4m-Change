<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
requireLogin();
include '../../templates/header.php';
include '../../templates/navbar.php';

$id   = (int)($_GET['id'] ?? 0);
$role = currentUserRole();

$stmt = $pdo->prepare("
    SELECT cr.*, u.name AS pic_name, c.name AS creator_name
    FROM change_requests cr
    JOIN users u ON cr.pic_id = u.id
    JOIN users c ON cr.created_by = c.id
    WHERE cr.id = ? LIMIT 1
");
$stmt->execute([$id]);
$data = $stmt->fetch();
if (!$data) die('Data not found.');

$photos = $pdo->prepare("SELECT * FROM change_photos WHERE change_request_id = ? ORDER BY id");
$photos->execute([$id]); $photos = $photos->fetchAll();

$attachments = $pdo->prepare("SELECT * FROM change_attachments WHERE change_request_id = ? ORDER BY id");
$attachments->execute([$id]); $attachments = $attachments->fetchAll();

$histories = $pdo->prepare("
    SELECT h.*, u.name AS actor_name FROM change_histories h
    JOIN users u ON h.action_by = u.id
    WHERE h.change_request_id = ? ORDER BY h.action_at DESC
");
$histories->execute([$id]); $histories = $histories->fetchAll();

$approvalLogs = $pdo->prepare("
    SELECT ca.*, u.name AS approver_name FROM change_approvals ca
    JOIN users u ON ca.approver_id = u.id
    WHERE ca.change_request_id = ? ORDER BY ca.acted_at ASC
");
$approvalLogs->execute([$id]); $approvalLogs = $approvalLogs->fetchAll();

$beforePhoto = $afterPhoto = null;
foreach ($photos as $p) {
    if ($p['photo_type'] === 'before') $beforePhoto = $p;
    if ($p['photo_type'] === 'after')  $afterPhoto  = $p;
}

$canApproveNow = canApprove($role, $data['workflow_status'], $pdo, currentUserId(), $id);
$statusOrder   = ['Draft'=>0,'Submitted'=>1,'Manager Approved'=>2,'Closed'=>3,'Rejected'=>-1];
$curOrder      = $statusOrder[$data['workflow_status']] ?? 0;

$mgrApproval = $qcFinal = null;
foreach ($approvalLogs as $log) {
    if ($log['step'] === 'manager')  $mgrApproval = $log;
    if ($log['step'] === 'qc_final') $qcFinal     = $log;
}

$actionLabel = ['CREATE'=>'Created','UPDATE'=>'Updated','SUBMIT'=>'Submit',
                'RESUBMIT'=>'Resubmit','APPROVAL'=>'Approved','REJECTION'=>'Rejected','CLOSED'=>'Closed'];
$actionBadge = ['CREATE'=>'secondary','UPDATE'=>'secondary','SUBMIT'=>'primary',
                'RESUBMIT'=>'primary','APPROVAL'=>'success','REJECTION'=>'danger','CLOSED'=>'success'];

$isRejected = $data['workflow_status'] === 'Rejected';

$mgrApproval = $qcApproval = null;
foreach ($approvalLogs as $log) {
    if ($log['step'] === 'manager') $mgrApproval = $log;
    if ($log['step'] === 'qc')      $qcApproval  = $log;
}

$progressSteps = [
    ['label' => 'Submitter',     'appr' => null,        'rejHere' => false],
    ['label' => 'Manager Dept.', 'appr' => $mgrApproval,'rejHere' => $isRejected && $mgrApproval && $mgrApproval['status']==='Rejected'],
    ['label' => 'QC',            'appr' => $qcApproval, 'rejHere' => $isRejected && $qcApproval  && $qcApproval['status']==='Rejected'],
];

$statusOrder = ['Draft'=>0,'Submitted'=>1,'Manager Approved'=>2,'QC Approved'=>3,'Rejected'=>-1];

function stepCircle(int $cur, int $order, ?array $appr): string {
    if ($appr && $appr['status'] === 'Rejected')
        return '<div class="step-circle step-rejected">✗</div>';
    if ($cur > $order) return '<div class="step-circle step-done">✓</div>';
    if ($cur === $order) return '<div class="step-circle step-active">→</div>';
    return '<div class="step-circle step-pending">○</div>';
}
?>

<?php if (isset($_GET['updated'])): ?>
<div class="alert alert-success alert-dismissible fade show">Successfully processed.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger">An error occurred: <?= e($_GET['error']) ?></div>
<?php endif; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-3">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
        <div>
            <h3 class="mb-0">4M Change Details</h3>
            <div class="text-muted small mono"><?= e($data['change_no']) ?></div>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge text-bg-<?= workflowBadgeClass($data['workflow_status']) ?>" style="font-size:13px;padding:6px 12px">
            <?= e($data['workflow_status']) ?>
        </span>
        <?php if (in_array($role,['admin','manager'],true) && $data['workflow_status']==='Rejected'): ?>
        <a href="edit.php?id=<?= $data['id'] ?>" class="btn btn-outline-warning btn-sm">Edit & Resubmit</a>
        <?php endif; ?>
        <a href="export_pdf.php?id=<?= $data['id'] ?>" class="btn btn-sm" target="_blank"
            style="background:#f8f8f8;border-color:#ddd">
            📄 Export PDF
        </a>
    </div>
</div>

<!-- Approval Progress Bar -->
<div class="card card-soft mb-3">
    <div class="card-body" style="padding:20px 24px">
        <div class="apv-progress">
            <?php foreach ($progressSteps as $i => $step):
                $isDone   = !$isRejected && (
                    ($i === 0 && $curOrder >= 1) ||
                    ($i === 1 && $curOrder >= 2) ||
                    ($i === 2 && $curOrder >= 3)
                );
                $isRejHere = $step['rejHere'];

                if ($isRejHere)     $cls = 'apv-circle-rejected';
                elseif ($isDone)    $cls = 'apv-circle-done';
                elseif (!$isRejected && (
                    ($i === 1 && $curOrder === 1) ||
                    ($i === 2 && $curOrder === 2)
                ))                 $cls = 'apv-circle-active';
                else                $cls = 'apv-circle-pending';
            ?>
            <div class="apv-step">
                <div class="apv-circle <?= $cls ?>">
                    <?php if ($isRejHere): ?>✗
                    <?php elseif ($isDone): ?>✓
                    <?php else: ?>
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                        </svg>
                    <?php endif; ?>
                </div>
                <div class="apv-name"><?= $step['label'] ?></div>
                <?php if ($i === 0): ?>
                    <div class="apv-sub" style="color:#16a34a">Submitted</div>
                <?php elseif ($step['appr']): ?>
                    <div class="apv-sub" style="color:<?= $step['appr']['status']==='Approved' ? '#16a34a' : '#dc2626' ?>">
                        <?= $step['appr']['status']==='Approved' ? 'Approved' : 'Rejected' ?>
                    </div>
                <?php else: ?>
                    <div class="apv-sub">Pending</div>
                <?php endif; ?>
            </div>
            <?php if ($i < count($progressSteps) - 1): ?>
            <div class="apv-arrow <?= (!$isRejected && $curOrder > $i) ? 'apv-arrow-done' : '' ?>">→</div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.apv-progress { display:flex; align-items:center; justify-content:center; gap:12px; }
.apv-step { display:flex; flex-direction:column; align-items:center; gap:6px; min-width:80px; }
.apv-circle { width:60px; height:60px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:600; }
.apv-circle-pending  { background:#e5e7eb; color:#9ca3af; }
.apv-circle-active   { background:#dbeafe; color:#2563eb; }
.apv-circle-done     { background:#dcfce7; color:#16a34a; }
.apv-circle-rejected { background:#fee2e2; color:#dc2626; }
.apv-name { font-size:12px; font-weight:600; color:#374151; text-align:center; }
.apv-sub  { font-size:11px; color:#9ca3af; text-align:center; }
.apv-arrow { font-size:22px; color:#d1d5db; margin-bottom:28px; flex-shrink:0; }
.apv-arrow-done { color:#16a34a; }
</style>

<?php if ($data['workflow_status'] === 'Rejected' && $data['rejection_note']): ?>
<div class="rejection-banner mb-3">
    <div class="rb-title">Rejection Reason</div>
    <div class="rb-note"><?= e($data['rejection_note']) ?></div>
</div>
<?php endif; ?>

<!-- General Information & Approval Status -->
<div class="row g-3 mb-3">
    <div class="col-12">
    <div class="card card-soft">
        <div class="card-header bg-white fw-semibold">General Information</div>
        <div class="card-body">
            <div class="row g-0">
                <div class="col-md-6" style="border-right:1px solid #f1f3f5">
                    <div class="info-row"><div class="info-key">Change No</div><div class="info-val mono"><?= e($data['change_no']) ?></div></div>
                    <div class="info-row"><div class="info-key">4M Category</div><div class="info-val"><?= e($data['category_4m']) ?></div></div>
                    <div class="info-row"><div class="info-key">Part Name</div><div class="info-val"><?= e($data['part_name']) ?></div></div>
                    <div class="info-row"><div class="info-key">Part No</div><div class="info-val"><?= e($data['part_no'] ?: '—') ?></div></div>
                    <div class="info-row"><div class="info-key">PIC</div><div class="info-val"><?= e($data['pic_name']) ?></div></div>
                    <div class="info-row"><div class="info-key">Model</div><div class="info-val"><?= e($data['model'] ?: '—') ?></div></div>
                </div>
                <div class="col-md-6" style="padding-left:1px">
                    <div class="info-row"><div class="info-key">Location</div><div class="info-val"><?= e($data['implement_location'] ?: '—') ?></div></div>
                    <div class="info-row"><div class="info-key">Start Lot</div><div class="info-val"><?= e($data['start_lot_serial'] ?: '—') ?></div></div>
                    <div class="info-row"><div class="info-key">Confirm Customer</div><div class="info-val"><?= e($data['confirm_customer']) ?></div></div>
                    <div class="info-row"><div class="info-key">Judge Status</div><div class="info-val"><span class="badge text-bg-<?= judgeBadgeClass($data['judge_status']) ?>"><?= e($data['judge_status']) ?></span></div></div>
                    <div class="info-row"><div class="info-key">Created by</div><div class="info-val"><?= e($data['creator_name']) ?></div></div>
                    <div class="info-row"><div class="info-key">Created at</div><div class="info-val text-muted small"><?= date('d M Y H:i', strtotime($data['created_at'])) ?></div></div>
                    <div class="info-row"><div class="info-key">4M Change Date</div><div class="info-val"><?= $data['change_date'] ? date('d M Y', strtotime($data['change_date'])) : '—' ?></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Change Details -->
<div class="card card-soft mb-3">
    <div class="card-header bg-white fw-semibold">Change Details</div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-6"><div class="form-label">4M Change Item</div><div style="font-size:13px;line-height:1.6"><?= nl2br(e($data['change_item'] ?: '—')) ?></div></div>
            <div class="col-md-6"><div class="form-label">Action</div><div style="font-size:13px;line-height:1.6"><?= nl2br(e($data['action_plan'] ?: '—')) ?></div></div>
            <div class="col-md-6"><div class="form-label">Before</div><div style="font-size:13px;line-height:1.6"><?= nl2br(e($data['before_desc'] ?: '—')) ?></div></div>
            <div class="col-md-6"><div class="form-label">After</div><div style="font-size:13px;line-height:1.6"><?= nl2br(e($data['after_desc'] ?: '—')) ?></div></div>
            <?php if ($data['evidence_note']): ?>
            <div class="col-12"><div class="form-label">Evidence Note</div><div style="font-size:13px;line-height:1.6"><?= nl2br(e($data['evidence_note'])) ?></div></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Evidence Photos -->
<?php if ($beforePhoto || $afterPhoto): ?>
<div class="card card-soft mb-3">
    <div class="card-header bg-white fw-semibold">Evidence Photos</div>
    <div class="card-body">
        <div class="row g-3">
            <?php if ($beforePhoto): ?>
                <div class="col-md-6">
                    <div class="text-muted small mb-2 fw-semibold">BEFORE</div>
                    <img src="<?= APP_URL . '/' . e($beforePhoto['file_path']) ?>"
                        class="img-fluid rounded photo-thumb"
                        style="max-height:220px;object-fit:cover;width:100%;border:1px solid #e2e5ea;cursor:zoom-in"
                        onclick="openLightbox(this.src, 'Before')"
                        title="Click to enlarge">
                </div>
                <?php endif; ?>
                <?php if ($afterPhoto): ?>
                <div class="col-md-6">
                    <div class="text-muted small mb-2 fw-semibold">AFTER</div>
                    <img src="<?= APP_URL . '/' . e($afterPhoto['file_path']) ?>"
                        class="img-fluid rounded photo-thumb"
                        style="max-height:220px;object-fit:cover;width:100%;border:1px solid #e2e5ea;cursor:zoom-in"
                        onclick="openLightbox(this.src, 'After')"
                        title="Click to enlarge">
                </div>
                <?php endif; ?>
        </div>
    </div>
</div>

<!-- Lightbox -->
<div id="lightbox" onclick="closeLightbox()" style="
    display:none;position:fixed;inset:0;z-index:9999;
    background:rgba(0,0,0,0.85);
    align-items:center;justify-content:center;flex-direction:column;
    cursor:zoom-out;padding:24px">
    <div style="position:absolute;top:16px;right:20px;color:#fff;font-size:28px;cursor:pointer;line-height:1;user-select:none" onclick="closeLightbox()">×</div>
    <div id="lightboxLabel" style="color:rgba(255,255,255,0.6);font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;margin-bottom:12px"></div>
    <img id="lightboxImg" src="" style="max-width:92vw;max-height:88vh;object-fit:contain;border-radius:8px;box-shadow:0 8px 40px rgba(0,0,0,0.5)" onclick="event.stopPropagation()">
    <div style="color:rgba(255,255,255,0.35);font-size:12px;margin-top:12px">Click outside the photo or press ESC to close</div>
</div>

<script>
function openLightbox(src, label) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightboxLabel').textContent = label;
    const lb = document.getElementById('lightbox');
    lb.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').style.display = 'none';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLightbox();
});
</script>
<?php endif; ?>

<!-- Attachment -->
<?php if ($attachments): ?>
<div class="card card-soft mb-3">
    <div class="card-header bg-white fw-semibold">Other Attachments</div>
    <div class="card-body d-flex flex-wrap gap-2">
        <?php foreach ($attachments as $att): ?>
        <a href="<?= e(navLink($att['file_path'])) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
            📎 <?= e($att['file_name']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Approval Info Form — only shown for manager -->
<?php if ($canApproveNow): ?>
<div class="card card-soft mb-3">
    <div class="card-header bg-white fw-semibold">Approval Info</div>
    <div class="card-body">
        <form action="process_approval.php" method="POST" id="approvalForm">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            <input type="hidden" name="action" id="approvalAction" value="">
            <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Judge Status <span class="text-danger">*</span></label>
                    <div class="d-flex gap-3">
                        <?php foreach (['OK','NG','Pending'] as $j): ?>
                        <label class="d-flex align-items-center gap-1" style="cursor:pointer">
                            <input type="radio" name="judge_status" value="<?= $j ?>"
                                <?= $data['judge_status'] === $j ? 'checked' : '' ?> required>
                            <?= $j ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Confirm Customer <span class="text-danger">*</span></label>
                    <div class="d-flex gap-3">
                        <?php foreach (['Need','Not Need'] as $c): ?>
                        <label class="d-flex align-items-center gap-1" style="cursor:pointer">
                            <input type="radio" name="confirm_customer" value="<?= $c ?>"
                                <?= $data['confirm_customer'] === $c ? 'checked' : '' ?> required>
                            <?= $c ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Evidence Note</label>
                    <textarea name="evidence_note" class="form-control" rows="2"
                        placeholder="Evidence note / additional remarks..."><?= e($data['evidence_note'] ?? '') ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Note (optional)</label>
                    <textarea name="note" class="form-control" rows="2" placeholder="Add a note..."></textarea>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-success w-50"
                    onclick="document.getElementById('approvalAction').value='approve';if(confirm('Approve this request?'))document.getElementById('approvalForm').submit()">
                    ✓ Approve
                </button>
                <button type="button" class="btn btn-outline-danger w-50"
                    onclick="document.getElementById('approvalAction').value='reject';if(confirm('Reject this request?'))document.getElementById('approvalForm').submit()">
                    ✗ Reject
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Activity History -->
<div class="card card-soft">
    <div class="card-header bg-white fw-semibold">Activity History</div>
    <div class="card-body">
        <?php if (!$histories): ?>
        <div class="text-muted small">No history available.</div>
        <?php endif; ?>
        <?php foreach ($histories as $h): ?>
        <div class="history-item">
            <div style="flex-shrink:0;padding-top:1px">
                <span class="badge text-bg-<?= $actionBadge[$h['action_type']] ?? 'secondary' ?>">
                    <?= $actionLabel[$h['action_type']] ?? $h['action_type'] ?>
                </span>
            </div>
            <div class="h-info">
                <div class="h-title"><?= e($h['action_note']) ?></div>
                <div class="h-actor"><?= e($h['actor_name']) ?></div>
            </div>
            <div class="h-time"><?= date('d M Y H:i', strtotime($h['action_at'])) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include '../../templates/footer.php'; ?>