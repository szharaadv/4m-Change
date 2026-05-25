<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
requireLogin();
include '../../templates/header.php';
include '../../templates/navbar.php';

$role   = currentUserRole();
$userId = currentUserId();
$user   = currentUser();

$total        = (int)$pdo->query("SELECT COUNT(*) FROM change_requests")->fetchColumn();
$closedMonth  = (int)$pdo->query("SELECT COUNT(*) FROM change_requests WHERE workflow_status='Closed' AND MONTH(updated_at)=MONTH(NOW()) AND YEAR(updated_at)=YEAR(NOW())")->fetchColumn();
$rejected     = (int)$pdo->query("SELECT COUNT(*) FROM change_requests WHERE workflow_status='Rejected'")->fetchColumn();
$needCustomer = (int)$pdo->query("SELECT COUNT(*) FROM change_requests WHERE confirm_customer='Need'")->fetchColumn();
$needCount    = getNeedCount($pdo, $role, $userId);

$pipeSubmitted = (int)$pdo->query("SELECT COUNT(*) FROM change_requests WHERE workflow_status='Submitted'")->fetchColumn();
$pipeMgr       = (int)$pdo->query("SELECT COUNT(*) FROM change_requests WHERE workflow_status='Manager Approved'")->fetchColumn();
$pipeQc        = (int)$pdo->query("SELECT COUNT(*) FROM change_requests WHERE workflow_status='QC Approved'")->fetchColumn();
$pipeClosed    = (int)$pdo->query("SELECT COUNT(*) FROM change_requests WHERE workflow_status='Closed'")->fetchColumn();

$recent = $pdo->query("
    SELECT cr.*, u.name AS pic_name
    FROM change_requests cr
    JOIN users u ON cr.pic_id = u.id
    ORDER BY cr.created_at DESC
    LIMIT 5
")->fetchAll();

$activities = $pdo->query("
    SELECT h.action_type, h.action_note, h.action_at, u.name AS actor_name
    FROM change_histories h
    JOIN users u ON h.action_by = u.id
    ORDER BY h.action_at DESC
    LIMIT 8
")->fetchAll();

$hour = (int)date('G');
$greeting = $hour < 12 ? 'Selamat pagi' : ($hour < 17 ? 'Selamat siang' : 'Selamat sore');
$days = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$months = ['January'=>'Jan','February'=>'Feb','March'=>'Mar','April'=>'Apr','May'=>'Mei','June'=>'Jun','July'=>'Jul','August'=>'Agt','September'=>'Sep','October'=>'Okt','November'=>'Nov','December'=>'Des'];
$dayName = $days[date('l')] ?? date('l');
$monthName = $months[date('F')] ?? date('F');
$dateStr = $dayName . ', ' . date('d') . ' ' . $monthName . ' ' . date('Y');

$actDot = ['CREATE'=>'dot-gray','UPDATE'=>'dot-gray','SUBMIT'=>'dot-amber','RESUBMIT'=>'dot-amber','APPROVAL'=>'dot-green','REJECTION'=>'dot-red','CLOSED'=>'dot-green'];
?>

<!-- Greeting -->
<div class="dash-greeting">
    <div class="dash-greeting-name"><?= $greeting ?>, <?= e($user['name'] ?? 'User') ?> 👋</div>
    <div class="dash-greeting-date"><?= $dateStr ?> · Sistem 4M Change aktif</div>
</div>

<!-- Stat Cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div>
            <div class="stat-label">Total Change</div>
            <div class="stat-val"><?= $total ?></div>
        </div>
        <svg width="38" height="38" viewBox="0 0 38 38"><circle cx="19" cy="19" r="15" fill="none" stroke="#f0f0f0" stroke-width="4"/><circle cx="19" cy="19" r="15" fill="none" stroke="#D0021B" stroke-width="4" stroke-dasharray="<?= min($total * 8, 94) ?> 94" stroke-linecap="round" transform="rotate(-90 19 19)"/></svg>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-label">Closed Bulan Ini</div>
            <div class="stat-val text-success"><?= $closedMonth ?></div>
        </div>
        <svg width="38" height="38" viewBox="0 0 38 38"><circle cx="19" cy="19" r="15" fill="none" stroke="#f0f0f0" stroke-width="4"/><circle cx="19" cy="19" r="15" fill="none" stroke="#16a34a" stroke-width="4" stroke-dasharray="<?= min($closedMonth * 15, 94) ?> 94" stroke-linecap="round" transform="rotate(-90 19 19)"/></svg>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-label">Need Customer Confirm</div>
            <div class="stat-val"><?= $needCustomer ?></div>
        </div>
        <svg width="38" height="38" viewBox="0 0 38 38"><circle cx="19" cy="19" r="15" fill="none" stroke="#f0f0f0" stroke-width="4"/><circle cx="19" cy="19" r="15" fill="none" stroke="#f59e0b" stroke-width="4" stroke-dasharray="<?= min($needCustomer * 10, 94) ?> 94" stroke-linecap="round" transform="rotate(-90 19 19)"/></svg>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-label">Rejected (Open)</div>
            <div class="stat-val text-danger"><?= $rejected ?></div>
        </div>
        <svg width="38" height="38" viewBox="0 0 38 38"><circle cx="19" cy="19" r="15" fill="none" stroke="#f0f0f0" stroke-width="4"/><circle cx="19" cy="19" r="15" fill="none" stroke="#D0021B" stroke-width="4" stroke-dasharray="<?= min($rejected * 15, 94) ?> 94" stroke-linecap="round" transform="rotate(-90 19 19)"/></svg>
    </div>
</div>

<!-- Approval Pipeline -->
<div class="pipeline-card">
    <div class="pipeline-title">Approval pipeline</div>
    <div class="pipeline-flow">
        <div class="pipeline-step ps-submitted">
            <div class="pipeline-num pn-amber"><?= $pipeSubmitted ?></div>
            <div class="pipeline-lbl">Submitted</div>
        </div>
        <div class="pipeline-arrow">→</div>
        <div class="pipeline-step ps-mgr">
            <div class="pipeline-num pn-red"><?= $pipeMgr ?></div>
            <div class="pipeline-lbl">Manager Approved</div>
        </div>
        <div class="pipeline-arrow">→</div>
        <div class="pipeline-step ps-qc">
            <div class="pipeline-num pn-blue"><?= $pipeQc ?></div>
            <div class="pipeline-lbl">QC Approved</div>
        </div>
        <div class="pipeline-arrow">→</div>
        <div class="pipeline-step ps-closed">
            <div class="pipeline-num pn-green"><?= $pipeClosed ?></div>
            <div class="pipeline-lbl">Closed</div>
        </div>
    </div>
</div>

<?php if ($needCount > 0): ?>
<div class="alert alert-warning">
    <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" style="flex-shrink:0"><path d="M8 15A7 7 0 118 1a7 7 0 010 14zm0 1A8 8 0 108 0a8 8 0 000 16z"/><path d="M7.002 11a1 1 0 112 0 1 1 0 01-2 0zM7.1 4.995a.905.905 0 111.8 0l-.35 3.507a.552.552 0 01-1.1 0L7.1 4.995z"/></svg>
    Ada <strong style="margin:0 3px"><?= $needCount ?></strong> permohonan yang menunggu approval Anda.
    <a href="/../modules/changes/index.php?tab=need_approval" class="btn btn-sm" style="margin-left:auto;background:#D0021B;color:#fff;border-color:#D0021B">Lihat sekarang</a>
</div>
<?php endif; ?>

<!-- Bottom Grid: Tabel + Activity -->
<div class="dash-bottom">
    <div class="table-wrap">
        <div class="card-header">
            Change terbaru
            <a href="/../modules/changes/index.php" class="btn btn-sm">Lihat semua</a>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Change No</th>
                    <th>Kategori</th>
                    <th>Part Name</th>
                    <th>PIC</th>
                    <th>Judge</th>
                    <th>Status</th>
                    <th>Tanggal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$recent): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:24px">Belum ada data.</td></tr>
                <?php endif; ?>
                <?php foreach ($recent as $row): ?>
                <tr class="clickable" onclick="location.href='/../modules/changes/detail.php?id=<?= $row['id'] ?>'">
                    <td class="mono"><?= e($row['change_no']) ?></td>
                    <td><?= e($row['category_4m']) ?></td>
                    <td><?= e($row['part_name']) ?></td>
                    <td><?= e($row['pic_name']) ?></td>
                    <td><span class="badge badge-<?= strtolower($row['judge_status']) ?>"><?= e($row['judge_status']) ?></span></td>
                    <td><?php
                        $sc = ['Draft'=>'draft','Submitted'=>'submitted','Manager Approved'=>'manager','QC Approved'=>'qc','Closed'=>'closed','Rejected'=>'rejected'];
                        $cls = $sc[$row['workflow_status']] ?? 'draft';
                    ?><span class="badge badge-<?= $cls ?>"><?= e($row['workflow_status']) ?></span></td>
                    <td style="color:var(--muted)"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Activity Feed -->
    <div class="activity-card">
        <div class="card-header">Aktivitas terbaru</div>
        <?php if (!$activities): ?>
        <div style="padding:16px;font-size:12px;color:var(--muted)">Belum ada aktivitas.</div>
        <?php endif; ?>
        <?php foreach ($activities as $act): ?>
        <div class="act-item">
            <div class="act-dot <?= $actDot[$act['action_type']] ?? 'dot-gray' ?>"></div>
            <div>
                <div class="act-text"><?= e($act['action_note']) ?></div>
                <div class="act-time"><?= e($act['actor_name']) ?> · <?= date('d M H:i', strtotime($act['action_at'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include '../../templates/footer.php'; ?>
