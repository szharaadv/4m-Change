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
$pendingApprovals = canApproveAny() ? getPendingApprovals($pdo, $role, $userId) : [];

$pipeSubmitted = (int)$pdo->query("SELECT COUNT(*) FROM change_requests WHERE workflow_status='Submitted'")->fetchColumn();
$pipeMgr       = (int)$pdo->query("SELECT COUNT(*) FROM change_requests WHERE workflow_status='Manager Approved'")->fetchColumn();
$pipeQc        = (int)$pdo->query("SELECT COUNT(*) FROM change_requests WHERE workflow_status='QC Approved'")->fetchColumn();
$pipeClosed    = (int)$pdo->query("SELECT COUNT(*) FROM change_requests WHERE workflow_status='Closed'")->fetchColumn();

$recent = $pdo->query("
    SELECT cr.*, u.name AS pic_name
    FROM change_requests cr
    JOIN users u ON cr.pic_id = u.id
    ORDER BY cr.created_at DESC
    LIMIT 15
")->fetchAll();

$activities = $pdo->query("
    SELECT h.action_type, h.action_note, h.action_at, u.name AS actor_name
    FROM change_histories h
    JOIN users u ON h.action_by = u.id
    ORDER BY h.action_at DESC
    LIMIT 20
")->fetchAll();

$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$dateStr = date('l') . ', ' . date('d') . ' ' . date('M') . ' ' . date('Y');

$actDot = ['CREATE'=>'dot-gray','UPDATE'=>'dot-gray','SUBMIT'=>'dot-amber','RESUBMIT'=>'dot-amber','APPROVAL'=>'dot-green','REJECTION'=>'dot-red','CLOSED'=>'dot-green'];

// ============================================================================
// ADD: Analytics data (NEW)
// ============================================================================
$analyticsStats = null;

try {
    $statsResult = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM change_requests) as total_all,
            (SELECT COUNT(*) FROM change_requests WHERE workflow_status IN ('Manager Approved', 'QC Approved', 'Closed')) as total_approved,
            (SELECT COUNT(*) FROM change_requests WHERE workflow_status = 'Rejected') as total_rejected,
            (SELECT AVG(DATEDIFF(updated_at, created_at)) FROM change_requests WHERE workflow_status IN ('Manager Approved', 'QC Approved', 'Closed')) as avg_approval_days
    ");
    $analyticsStats = $statsResult->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $analyticsStats = [
        'total_all' => 0,
        'total_approved' => 0,
        'total_rejected' => 0,
        'avg_approval_days' => 0
    ];
}
?>

<div class="dashboard-flex">

<!-- Greeting -->
<div class="dash-greeting">
    <div class="dash-greeting-name"><?= $greeting ?>, <?= e($user['name'] ?? 'User') ?> 👋</div>
    <div class="dash-greeting-date"><?= $dateStr ?> · 4M Change System active</div>
</div>

<?php if ($pendingApprovals): ?>
<!-- Pending Approval Popup -->
<div class="modal fade" id="pendingApprovalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border:none;border-radius:12px;overflow:hidden">
            <div class="modal-header" style="background:#D0021B;color:#fff;border:none">
                <h5 class="modal-title" style="font-weight:600">🔔 Awaiting Your Approval</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:0">
                <div style="padding:16px 20px;background:#fff8f8;border-bottom:1px solid #f1f1f1;font-size:13px;color:#555">
                    There <?= count($pendingApprovals) === 1 ? 'is' : 'are' ?> <strong style="color:#D0021B"><?= count($pendingApprovals) ?></strong> 4M Change request(s) awaiting your approval.
                </div>
                <div style="max-height:320px;overflow-y:auto">
                    <?php foreach ($pendingApprovals as $pa): ?>
                    <a href="<?= navLink('modules/changes/detail.php') ?>?id=<?= $pa['id'] ?>" style="text-decoration:none;color:inherit">
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-bottom:1px solid #f1f1f1;transition:background .15s" onmouseover="this.style.background='#f9f9f9'" onmouseout="this.style.background='transparent'">
                            <div>
                                <div style="font-weight:600;font-size:13px;color:#1f2937"><?= e($pa['part_name']) ?></div>
                                <div style="font-size:12px;color:#888;margin-top:2px">
                                    <span class="mono"><?= e($pa['change_no']) ?></span> · <?= e($pa['category_4m']) ?> · by <?= e($pa['submitter_name']) ?>
                                </div>
                            </div>
                            <span style="font-size:11px;color:#D0021B;font-weight:600;white-space:nowrap;margin-left:12px">View →</span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer" style="border:none;padding:14px 20px">
                <button type="button" class="btn" data-bs-dismiss="modal" style="background:#f5f5f5">Later</button>
                <a href="<?= navLink('modules/changes/index.php') ?>?tab=need_approval" class="btn" style="background:#D0021B;color:#fff">View All</a>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('pendingApprovalModal');
    if (!modalEl) return;

    var signature = <?= json_encode(implode(',', array_column($pendingApprovals, 'id'))) ?>;
    var storageKey = 'dismissedApprovals_<?= (int)$userId ?>';
    var dismissed = sessionStorage.getItem(storageKey);

    if (dismissed !== signature) {
        var modal = new bootstrap.Modal(modalEl);
        modal.show();
        modalEl.addEventListener('hidden.bs.modal', function () {
            sessionStorage.setItem(storageKey, signature);
        });
    }
});
</script>
<?php endif; ?>

<!-- Stat Cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div>
            <div class="stat-label">Total Change</div>
            <div class="stat-val"><?= $total ?></div>
        </div>
        <div class="stat-icon si-red">
            <svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor"><path d="M14.5 3a.5.5 0 01.5.5v9a.5.5 0 01-.5.5h-13a.5.5 0 01-.5-.5v-9a.5.5 0 01.5-.5h13zm-13-1A1.5 1.5 0 000 3.5v9A1.5 1.5 0 001.5 14h13a1.5 1.5 0 001.5-1.5v-9A1.5 1.5 0 0014.5 2h-13zM3 6.5a.5.5 0 01.5-.5h9a.5.5 0 010 1h-9a.5.5 0 01-.5-.5zm0 3a.5.5 0 01.5-.5h9a.5.5 0 010 1h-9a.5.5 0 01-.5-.5z"/></svg>
        </div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-label">Closed This Month</div>
            <div class="stat-val text-success"><?= $closedMonth ?></div>
        </div>
        <div class="stat-icon si-green">
            <svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor"><path d="M13.854 3.646a.5.5 0 010 .708l-7 7a.5.5 0 01-.708 0l-3.5-3.5a.5.5 0 11.708-.708L6.5 10.293l6.646-6.647a.5.5 0 01.708 0z"/></svg>
        </div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-label">Need Customer Confirm</div>
            <div class="stat-val"><?= $needCustomer ?></div>
        </div>
        <div class="stat-icon si-amber">
            <svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor"><path d="M8 15A7 7 0 118 1a7 7 0 010 14zm0 1A8 8 0 108 0a8 8 0 000 16z"/><path d="M8 4a.5.5 0 01.5.5v4a.5.5 0 01-.146.354l-2.5 2.5a.5.5 0 01-.708-.708L7.5 8.293V4.5A.5.5 0 018 4z"/></svg>
        </div>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-label">Rejected (Open)</div>
            <div class="stat-val text-danger"><?= $rejected ?></div>
        </div>
        <div class="stat-icon si-danger">
            <svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor"><path d="M4.646 4.646a.5.5 0 01.708 0L8 7.293l2.646-2.647a.5.5 0 01.708.708L8.707 8l2.647 2.646a.5.5 0 01-.708.708L8 8.707l-2.646 2.647a.5.5 0 01-.708-.708L7.293 8 4.646 5.354a.5.5 0 010-.708z"/></svg>
        </div>
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
    There <?= $needCount === 1 ? 'is' : 'are' ?> <strong style="margin:0 3px"><?= $needCount ?></strong> request(s) awaiting your approval.
    <a href="<?= navLink('modules/changes/index.php') ?>?tab=need_approval" class="btn btn-sm" style="margin-left:auto;background:#D0021B;color:#fff;border-color:#D0021B">View now</a>
</div>
<?php endif; ?>

<!-- Bottom Grid: Tabel + Activity -->
<div class="dash-bottom">
    <div class="table-wrap">
        <div class="card-header">
            Recent Changes
            <a href="<?= navLink('modules/changes/index.php') ?>" class="btn btn-sm">View all</a>
        </div>
        <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Change No</th>
                    <th>Category</th>
                    <th>Part Name</th>
                    <th>Description</th>
                    <th>PIC</th>
                    <th>Judge</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$recent): ?>
                <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:24px">No data yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($recent as $row): ?>
                <tr class="clickable" onclick="location.href='<?= navLink('modules/changes/detail.php') ?>?id=<?= $row['id'] ?>'">
                    <td class="mono"><?= e($row['change_no']) ?></td>
                    <td><?= e($row['category_4m']) ?></td>
                    <td><?= e($row['part_name']) ?></td>
                    <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e($row['change_item']) ?>"><?= e($row['change_item'] ?: '—') ?></td>
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
    </div>

    <!-- Activity Feed -->
    <div class="activity-card">
        <div class="card-header">Recent Activity</div>
        <div class="activity-scroll">
        <?php if (!$activities): ?>
        <div style="padding:16px;font-size:12px;color:var(--muted)">No activity yet.</div>
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
</div>

</div><!-- .dashboard-flex -->

<!-- Include Chart.js from CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>

<!-- Include Analytics CSS -->
<link rel="stylesheet" href="css/analytics.css">

<!-- Analytics JavaScript -->
<script>
let charts = {};

// Load analytics on page load
document.addEventListener('DOMContentLoaded', function() {
    loadAnalytics();
    setInterval(loadAnalytics, 5 * 60 * 1000);
});

function loadAnalytics() {
    const startDate = document.getElementById('analyticsStartDate').value;
    const endDate = document.getElementById('analyticsEndDate').value;

    loadApprovalStats(startDate, endDate);
    loadChartData('monthly_trend', 'monthlyTrendChart', 'line', startDate, endDate);
    loadChartData('category_distribution', 'categoryDistChart', 'doughnut', startDate, endDate);
    loadChartData('approval_time', 'approvalTimeChart', 'bar', startDate, endDate);
    loadChartData('status_pipeline', 'statusPipelineChart', 'bar', startDate, endDate);
    loadChartData('rejection_reasons', 'rejectionReasonsChart', 'bar', startDate, endDate);
}

function loadApprovalStats(startDate, endDate) {
    fetch(`api/get_chart_data.php?action=approval_stats&start_date=${startDate}&end_date=${endDate}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                const stats = result.data;
                document.getElementById('totalSubmitted').textContent = stats.total_submitted;
                document.getElementById('approvedRate').textContent = stats.total_submitted > 0 
                    ? ((stats.total_approved / stats.total_submitted) * 100).toFixed(1) + '%'
                    : '0%';
                document.getElementById('approvedCount').textContent = stats.total_approved + ' approved';
                document.getElementById('rejectionRate').textContent = stats.rejection_rate_pct + '%';
                document.getElementById('rejectionCount').textContent = stats.total_rejected + ' rejected';
                document.getElementById('avgApprovalDays').textContent = stats.avg_approval_days;
            }
        })
        .catch(error => console.error('Error loading stats:', error));
}

function loadChartData(action, canvasId, chartType, startDate, endDate) {
    const containerMap = {
        'monthly_trend': 'monthlyTrendContainer',
        'category_distribution': 'categoryDistContainer',
        'approval_time': 'approvalTimeContainer',
        'status_pipeline': 'statusPipelineContainer',
        'rejection_reasons': 'rejectionReasonsContainer'
    };
    
    const container = document.getElementById(containerMap[action]);
    if (!container) return;

    container.innerHTML = '<div class="spinner"></div>';

    fetch(`api/get_chart_data.php?action=${action}&start_date=${startDate}&end_date=${endDate}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                renderChart(canvasId, chartType, result.data, container);
            } else {
                container.innerHTML = `<div style="color: #dc2626; padding: 20px;">Error: ${result.error}</div>`;
            }
        })
        .catch(error => {
            console.error('Error loading chart:', error);
            container.innerHTML = `<div style="color: #dc2626; padding: 20px;">Failed to load data</div>`;
        });
}

function renderChart(canvasId, chartType, data, container) {
    const canvas = document.getElementById(canvasId);
    canvas.style.display = 'block';
    container.parentElement.insertBefore(canvas, container);
    container.remove();

    if (charts[canvasId]) {
        charts[canvasId].destroy();
    }

    const ctx = canvas.getContext('2d');
    charts[canvasId] = new Chart(ctx, {
        type: chartType,
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: (chartType === 'doughnut' || chartType === 'pie') ? 'bottom' : 'top',
                    labels: {
                        font: { size: 12 },
                        padding: 15
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    font: { size: 12 }
                }
            },
            scales: (chartType === 'doughnut' || chartType === 'pie') ? {} : {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 },
                    grid: { color: 'rgba(0, 0, 0, 0.05)' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
}

function exportDashboardPDF() {
    alert('PDF export will be available in a future release.\n\nUse: Print (Ctrl+P) → Save as PDF');
}
</script>

<?php include '../../templates/footer.php'; ?>