<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
require_once '../../helpers/mailer.php';
require_once '../../helpers/audit.php';
requireLogin();

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

$isManagerStep = $role === 'manager' && $cr['workflow_status'] === 'Submitted';
$isQcStep      = $role === 'qc'      && $cr['workflow_status'] === 'Manager Approved';
$isQcFinal     = $role === 'qc'      && $cr['workflow_status'] === 'QC Approved';

if (!$isManagerStep && !$isQcStep && !$isQcFinal) {
    header('Location: detail.php?id=' . $id . '&error=not_your_turn');
    exit;
}

try {
    $pdo->beginTransaction();

    $changeNo  = $cr['change_no'];
    $detailUrl = "http://" . $_SERVER['HTTP_HOST'] . "/4m-change/modules/changes/detail.php?id=$id";

    if ($action === 'approve') {
        if ($isManagerStep) {
            $newStatus  = 'Manager Approved';
            $step       = 'manager';
            $actionType = 'APPROVAL';
            $actionNote = 'Disetujui oleh Manager Department';
        } elseif ($isQcStep) {
            $newStatus  = 'QC Approved';
            $step       = 'qc';
            $actionType = 'APPROVAL';
            $actionNote = 'Disetujui oleh QC';
        } else {
            $newStatus  = 'Closed';
            $step       = 'qc_final';
            $actionType = 'CLOSED';
            $actionNote = 'Final submit oleh QC — permohonan ditutup';
        }

        $pdo->prepare("UPDATE change_requests SET
            workflow_status = ?,
            updated_by = ?,
            judge_status = ?,
            confirm_customer = ?,
            evidence_note = ?
            WHERE id = ?")
            ->execute([
                $newStatus,
                $userId,
                $_POST['judge_status']     ?? $cr['judge_status'],
                $_POST['confirm_customer'] ?? $cr['confirm_customer'],
                trim($_POST['evidence_note'] ?? '') ?: $cr['evidence_note'],
                $id
            ]);

        $pdo->prepare("INSERT INTO change_approvals (change_request_id, step, approver_id, status, note) VALUES (?, ?, ?, 'Approved', ?)")
            ->execute([$id, $step, $userId, $note ?: null]);

        $pdo->prepare("INSERT INTO change_histories (change_request_id, action_type, action_note, action_by) VALUES (?, ?, ?, ?)")
            ->execute([$id, $actionType, $actionNote . ($note ? ' | Catatan: ' . $note : ''), $userId]);

        $pdo->commit();

        // Audit log
        if ($newStatus === 'Closed') {
            writeAuditLog($pdo, 'CHANGE_CLOSED', "Final submit (Closed) — $changeNo");
        } else {
            writeAuditLog($pdo, 'CHANGE_APPROVED', "Approve $changeNo → $newStatus (step: $step)");
        }

        // Email notifications
        $noteRow = $note ? "<tr><td style='color:#888;padding:6px 0;font-size:12px'>Catatan</td><td style='padding:6px 0;font-size:12px'>$note</td></tr>" : '';

        if ($isManagerStep) {
            $qcUsers  = getQcEmails($pdo);
            $bodyHtml = "
                <p style='color:#444;font-size:13px;margin:0 0 16px'>Permohonan 4M Change berikut telah disetujui oleh <strong>Manager Department</strong> dan menunggu review QC.</p>
                <table style='width:100%;border-collapse:collapse;font-size:13px'>
                    <tr><td style='color:#888;padding:6px 0;width:140px'>Change No</td><td style='padding:6px 0;font-weight:600;font-family:monospace'>$changeNo</td></tr>
                    <tr><td style='color:#888;padding:6px 0'>Part Name</td><td style='padding:6px 0'>{$cr['part_name']}</td></tr>
                    <tr><td style='color:#888;padding:6px 0'>Kategori</td><td style='padding:6px 0'>{$cr['category_4m']}</td></tr>
                    $noteRow
                </table>
                <div style='margin:20px 0'>
                    <a href='$detailUrl' style='background:#D0021B;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600'>Lihat Detail & Approve</a>
                </div>";
            foreach ($qcUsers as $u) {
                sendMail($u['email'], $u['name'], "[4M Change] Menunggu Review QC — $changeNo", mailTemplate("Permohonan Siap Review QC", $bodyHtml));
            }

        } elseif ($isQcStep) {
            $qcUsers  = getQcEmails($pdo);
            $bodyHtml = "
                <p style='color:#444;font-size:13px;margin:0 0 16px'>Permohonan 4M Change berikut telah disetujui QC tahap pertama dan menunggu <strong>Final Submit QC</strong>.</p>
                <table style='width:100%;border-collapse:collapse;font-size:13px'>
                    <tr><td style='color:#888;padding:6px 0;width:140px'>Change No</td><td style='padding:6px 0;font-weight:600;font-family:monospace'>$changeNo</td></tr>
                    <tr><td style='color:#888;padding:6px 0'>Part Name</td><td style='padding:6px 0'>{$cr['part_name']}</td></tr>
                    <tr><td style='color:#888;padding:6px 0'>Kategori</td><td style='padding:6px 0'>{$cr['category_4m']}</td></tr>
                    $noteRow
                </table>
                <div style='margin:20px 0'>
                    <a href='$detailUrl' style='background:#D0021B;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600'>Lihat Detail & Final Submit</a>
                </div>";
            foreach ($qcUsers as $u) {
                sendMail($u['email'], $u['name'], "[4M Change] Menunggu Final Submit QC — $changeNo", mailTemplate("QC Approved — Final Submit", $bodyHtml));
            }

        } else {
            $submitter = getSubmitterEmail($pdo, $cr['created_by']);
            if ($submitter) {
                $bodyHtml = "
                    <p style='color:#444;font-size:13px;margin:0 0 16px'>Permohonan 4M Change telah <strong style='color:#16a34a'>selesai diproses (Closed)</strong>.</p>
                    <table style='width:100%;border-collapse:collapse;font-size:13px'>
                        <tr><td style='color:#888;padding:6px 0;width:140px'>Change No</td><td style='padding:6px 0;font-weight:600;font-family:monospace'>$changeNo</td></tr>
                        <tr><td style='color:#888;padding:6px 0'>Part Name</td><td style='padding:6px 0'>{$cr['part_name']}</td></tr>
                        <tr><td style='color:#888;padding:6px 0'>Kategori</td><td style='padding:6px 0'>{$cr['category_4m']}</td></tr>
                        <tr><td style='color:#888;padding:6px 0'>Status</td><td style='padding:6px 0;color:#16a34a;font-weight:600'>Closed ✓</td></tr>
                        $noteRow
                    </table>
                    <div style='margin:20px 0'>
                        <a href='$detailUrl' style='background:#16a34a;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600'>Lihat Detail</a>
                    </div>";
                sendMail($submitter['email'], $submitter['name'], "[4M Change] Permohonan Selesai (Closed) — $changeNo", mailTemplate("Permohonan Closed", $bodyHtml, '#16a34a'));
            }
        }

    } else {
        $step = $isManagerStep ? 'manager' : ($isQcStep ? 'qc' : 'qc_final');

        $pdo->prepare("UPDATE change_requests SET
            workflow_status = 'Rejected',
            rejected_by = ?,
            rejected_at = NOW(),
            rejection_note = ?,
            updated_by = ?,
            judge_status = ?,
            confirm_customer = ?,
            evidence_note = ?
            WHERE id = ?")
            ->execute([
                $userId,
                $note,
                $userId,
                $_POST['judge_status']     ?? $cr['judge_status'],
                $_POST['confirm_customer'] ?? $cr['confirm_customer'],
                trim($_POST['evidence_note'] ?? '') ?: $cr['evidence_note'],
                $id
            ]);

        $pdo->prepare("INSERT INTO change_approvals (change_request_id, step, approver_id, status, note) VALUES (?, ?, ?, 'Rejected', ?)")
            ->execute([$id, $step, $userId, $note ?: null]);

        $pdo->prepare("INSERT INTO change_histories (change_request_id, action_type, action_note, action_by) VALUES (?, 'REJECTION', ?, ?)")
            ->execute([$id, 'Ditolak oleh ' . stepLabel($step) . ($note ? ' | Alasan: ' . $note : ''), $userId]);

        $pdo->commit();

        // Audit log
        writeAuditLog($pdo, 'CHANGE_REJECTED', "Reject $changeNo (step: $step)" . ($note ? " — Alasan: $note" : ''));

        $submitter = getSubmitterEmail($pdo, $cr['created_by']);
        if ($submitter) {
            $alasan   = $note ? "<tr><td style='color:#888;padding:6px 0;width:140px'>Alasan</td><td style='padding:6px 0;color:#991b1b'>" . htmlspecialchars($note) . "</td></tr>" : '';
            $bodyHtml = "
                <p style='color:#444;font-size:13px;margin:0 0 16px'>Permohonan 4M Change telah <strong style='color:#991b1b'>ditolak</strong> oleh " . htmlspecialchars(stepLabel($step)) . ".</p>
                <table style='width:100%;border-collapse:collapse;font-size:13px'>
                    <tr><td style='color:#888;padding:6px 0;width:140px'>Change No</td><td style='padding:6px 0;font-weight:600;font-family:monospace'>$changeNo</td></tr>
                    <tr><td style='color:#888;padding:6px 0'>Part Name</td><td style='padding:6px 0'>{$cr['part_name']}</td></tr>
                    <tr><td style='color:#888;padding:6px 0'>Kategori</td><td style='padding:6px 0'>{$cr['category_4m']}</td></tr>
                    $alasan
                </table>
                <p style='color:#444;font-size:12px;margin:16px 0 0'>Silakan login ke sistem untuk melihat detail dan melakukan edit & resubmit.</p>
                <div style='margin:20px 0'>
                    <a href='$detailUrl' style='background:#991b1b;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600'>Lihat Detail & Resubmit</a>
                </div>";
            sendMail($submitter['email'], $submitter['name'], "[4M Change] Permohonan Ditolak — $changeNo", mailTemplate("Permohonan Ditolak", $bodyHtml, '#991b1b'));
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