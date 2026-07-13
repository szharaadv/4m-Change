<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
require_once '../../helpers/mailer.php';
require_once '../../helpers/audit.php';
requireLogin();
verifyCsrf();

$id     = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$note   = trim($_POST['note'] ?? '');

if (!$id || !in_array($action, ['approve', 'reject'], true)) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM change_requests WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$cr = $stmt->fetch();

if (!$cr) {
    header('Location: index.php');
    exit;
}

$role   = currentUserRole();
$userId = currentUserId();

$isManagerStep = false;
$isQcStep      = false;

if ($role === 'manager' && $cr['workflow_status'] === 'Submitted') {
    $chkStmt = $pdo->prepare("
        SELECT 1 FROM users u
        JOIN department_managers dm ON dm.department = u.department AND dm.manager_id = ?
        WHERE u.id = ? LIMIT 1
    ");
    $chkStmt->execute([$userId, $cr['created_by']]);
    $isManagerStep = (bool)$chkStmt->fetch();
}

if ($role === 'qc' && $cr['workflow_status'] === 'Manager Approved') {
    $chkStmt = $pdo->prepare("
        SELECT 1 FROM users u
        JOIN department_qc dq ON dq.department = u.department AND dq.qc_id = ?
        WHERE u.id = ? LIMIT 1
    ");
    $chkStmt->execute([$userId, $cr['created_by']]);
    $isQcStep = (bool)$chkStmt->fetch();
}

if (!$isManagerStep && !$isQcStep) {
    header('Location: detail.php?id=' . $id . '&error=not_your_turn');
    exit;
}

try {
    $pdo->beginTransaction();

    $changeNo  = $cr['change_no'];
    $detailUrl = APP_URL . "/modules/changes/detail.php?id=$id";

    if ($action === 'approve') {

        if ($isManagerStep) {
            $newStatus  = 'Manager Approved';
            $step       = 'manager';
            $actionType = 'APPROVAL';
            $actionNote = 'Approved by Manager Department';
        } else {
            $newStatus  = 'QC Approved';
            $step       = 'qc';
            $actionType = 'APPROVAL';
            $actionNote = 'Approved by QC';
        }

        $pdo->prepare("UPDATE change_requests SET
            workflow_status  = ?,
            updated_by       = ?,
            judge_status     = ?,
            confirm_customer = ?,
            evidence_note    = ?
            WHERE id = ?")
            ->execute([
                $newStatus,
                $userId,
                $_POST['judge_status']     ?? $cr['judge_status'],
                $_POST['confirm_customer'] ?? $cr['confirm_customer'],
                trim($_POST['evidence_note'] ?? '') ?: $cr['evidence_note'],
                $id,
            ]);

        $pdo->prepare("INSERT INTO change_approvals (change_request_id, step, approver_id, status, note) VALUES (?, ?, ?, 'Approved', ?)")
            ->execute([$id, $step, $userId, $note ?: null]);

        $pdo->prepare("INSERT INTO change_histories (change_request_id, action_type, action_note, action_by) VALUES (?, ?, ?, ?)")
            ->execute([$id, $actionType, $actionNote . ($note ? ' | Note: ' . $note : ''), $userId]);

        $pdo->commit();

        writeAuditLog($pdo, 'CHANGE_APPROVED', "Approve $changeNo → $newStatus (step: $step)");

        $partName = $cr['part_name'];
        $category = $cr['category_4m'];

        if ($isManagerStep) {
            // Get submitter department → route to the QC handling that department
            $deptStmt = $pdo->prepare("SELECT department FROM users WHERE id = ?");
            $deptStmt->execute([$cr['created_by']]);
            $dept = $deptStmt->fetchColumn() ?? '';

            $qcUsers = getDeptQc($pdo, $dept);
            if (empty($qcUsers)) {
                // Fallback: send to all QC
                $qcUsers = $pdo->query("SELECT name, email FROM users WHERE role='qc' AND is_active=1 AND email != ''")->fetchAll();
            }

            $infoTable = mailInfoTable([
                'Change No' => "<span style='font-family:monospace'>$changeNo</span>",
                'Part Name' => $partName,
                'Category'  => $category,
                'Note'      => $note ? htmlspecialchars($note) : null,
            ]);

            foreach ($qcUsers as $u) {
                $bodyHtml = mailGreeting($u['name']) . "
                    <p style='margin:0 0 14px'>The following 4M Change request has been approved by <strong>Manager Department</strong> and is now awaiting your approval.</p>
                    $infoTable
                    " . mailButton($detailUrl, 'View Detail & Approve') . "";
                sendMail($u['email'], $u['name'], changeSubject('Pending QC Approval', $category, $partName, $changeNo), mailTemplate('Ready for QC Review', $bodyHtml));
            }

            // Notify submitter — Manager Approved, moving to QC
            $submitter = getSubmitterEmail($pdo, $cr['created_by']);
            if ($submitter) {
                $infoTableSubmitter = mailInfoTable([
                    'Change No' => "<span style='font-family:monospace'>$changeNo</span>",
                    'Part Name' => $partName,
                    'Category'  => $category,
                    'Status'    => "<span style='color:#2563eb'>Manager Approved</span>",
                    'Note'      => $note ? htmlspecialchars($note) : null,
                ]);
                $bodySubmitter = mailGreeting($submitter['name']) . "
                    <p style='margin:0 0 14px'>Your 4M Change request has been <strong style='color:#2563eb'>approved by Manager Department</strong> and is now moving to the QC approval stage.</p>
                    $infoTableSubmitter
                    " . mailButton($detailUrl, 'View Detail', '#2563eb') . "";
                sendMail($submitter['email'], $submitter['name'], changeSubject('Manager Approved', $category, $partName, $changeNo), mailTemplate('Manager Approved', $bodySubmitter, '#2563eb'));
            }

        } else {
            // QC Approved → notify submitter
            $submitter = getSubmitterEmail($pdo, $cr['created_by']);
            if ($submitter) {
                $infoTable = mailInfoTable([
                    'Change No' => "<span style='font-family:monospace'>$changeNo</span>",
                    'Part Name' => $partName,
                    'Category'  => $category,
                    'Status'    => "<span style='color:#16a34a'>QC Approved ✓</span>",
                    'Note'      => $note ? htmlspecialchars($note) : null,
                ]);
                $bodyHtml = mailGreeting($submitter['name']) . "
                    <p style='margin:0 0 14px'>Your 4M Change request has been <strong style='color:#16a34a'>approved by QC (QC Approved)</strong>.</p>
                    $infoTable
                    " . mailButton($detailUrl, 'View Detail', '#16a34a') . "";
                sendMail($submitter['email'], $submitter['name'], changeSubject('QC Approved', $category, $partName, $changeNo), mailTemplate('QC Approved', $bodyHtml, '#16a34a'));
            }
        }

    } else {
        // REJECT
        $step = $isManagerStep ? 'manager' : 'qc';

        $pdo->prepare("UPDATE change_requests SET
            workflow_status  = 'Rejected',
            rejected_by      = ?,
            rejected_at      = NOW(),
            rejection_note   = ?,
            updated_by       = ?,
            judge_status     = ?,
            confirm_customer = ?,
            evidence_note    = ?
            WHERE id = ?")
            ->execute([
                $userId,
                $note,
                $userId,
                $_POST['judge_status']     ?? $cr['judge_status'],
                $_POST['confirm_customer'] ?? $cr['confirm_customer'],
                trim($_POST['evidence_note'] ?? '') ?: $cr['evidence_note'],
                $id,
            ]);

        $pdo->prepare("INSERT INTO change_approvals (change_request_id, step, approver_id, status, note) VALUES (?, ?, ?, 'Rejected', ?)")
            ->execute([$id, $step, $userId, $note ?: null]);

        $pdo->prepare("INSERT INTO change_histories (change_request_id, action_type, action_note, action_by) VALUES (?, 'REJECTION', ?, ?)")
            ->execute([$id, 'Rejected by ' . stepLabel($step) . ($note ? ' | Reason: ' . $note : ''), $userId]);

        $pdo->commit();

        writeAuditLog($pdo, 'CHANGE_REJECTED', "Reject $changeNo (step: $step)" . ($note ? " — Reason: $note" : ''));

        $submitter = getSubmitterEmail($pdo, $cr['created_by']);
        if ($submitter) {
            $infoTable = mailInfoTable([
                'Change No' => "<span style='font-family:monospace'>$changeNo</span>",
                'Part Name' => $cr['part_name'],
                'Category'  => $cr['category_4m'],
                'Reason'    => $note ? "<span style='color:#991b1b'>" . htmlspecialchars($note) . "</span>" : null,
            ]);
            $bodyHtml = mailGreeting($submitter['name']) . "
                <p style='margin:0 0 14px'>Your 4M Change request has been <strong style='color:#991b1b'>rejected</strong> by " . htmlspecialchars(stepLabel($step)) . ".</p>
                $infoTable
                <p style='margin:14px 0 0;font-size:12.5px;color:#6b7280'>Please log in to the system to view the details and edit & resubmit.</p>
                " . mailButton($detailUrl, 'View Detail & Resubmit', '#991b1b') . "";
            sendMail($submitter['email'], $submitter['name'], changeSubject('Rejected', $cr['category_4m'], $cr['part_name'], $changeNo), mailTemplate('Request Rejected', $bodyHtml, '#991b1b'));
        }
    }

    header('Location: detail.php?id=' . $id . '&updated=1');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[process_approval] ' . $e->getMessage());
    header('Location: detail.php?id=' . $id . '&error=' . urlencode($e->getMessage()));
    exit;
}