<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
requirePermission('changes.edit');
verifyCsrf();

$id = (int)($_POST['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT workflow_status FROM change_requests WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$current = $stmt->fetch();

if (!$current || $current['workflow_status'] !== 'Rejected') {
    header('Location: detail.php?id=' . $id);
    exit;
}

try {
    $pdo->beginTransaction();

    $pdo->prepare("UPDATE change_requests SET
        category_4m        = :category_4m,
        pic_id             = :pic_id,
        part_no            = :part_no,
        implement_location = :implement_location,
        part_name          = :part_name,
        model              = :model,
        start_lot_serial   = :start_lot_serial,
        change_item        = :change_item,
        action_plan        = :action_plan,
        before_desc        = :before_desc,
        after_desc         = :after_desc,
        judge_status       = :judge_status,
        confirm_customer   = :confirm_customer,
        evidence_note      = :evidence_note,
        workflow_status    = 'Submitted',
        rejection_note     = NULL,
        rejected_by        = NULL,
        rejected_at        = NULL,
        updated_by         = :updated_by
    WHERE id = :id")->execute([
        ':category_4m'        => $_POST['category_4m'],
        ':pic_id'             => $_POST['pic_id'],
        ':part_no'            => trim($_POST['part_no'] ?? '') ?: null,
        ':implement_location' => trim($_POST['implement_location'] ?? ''),
        ':part_name'          => trim($_POST['part_name'] ?? ''),
        ':model'              => trim($_POST['model'] ?? ''),
        ':start_lot_serial'   => trim($_POST['start_lot_serial'] ?? '') ?: null,
        ':change_item'        => trim($_POST['change_item'] ?? ''),
        ':action_plan'        => trim($_POST['action_plan'] ?? ''),
        ':before_desc'        => trim($_POST['before_desc'] ?? '') ?: null,
        ':after_desc'         => trim($_POST['after_desc'] ?? '') ?: null,
        ':judge_status'       => $_POST['judge_status'] ?? 'Pending',
        ':confirm_customer'   => $_POST['confirm_customer'] ?? 'Not Need',
        ':evidence_note'      => trim($_POST['evidence_note'] ?? '') ?: null,
        ':updated_by'         => currentUserId(),
        ':id'                 => $id,
    ]);

    $pdo->prepare("INSERT INTO change_histories (change_request_id, action_type, action_note, action_by) VALUES (?, 'UPDATE', 'Data updated after rejection', ?)")
        ->execute([$id, currentUserId()]);

    $pdo->prepare("INSERT INTO change_histories (change_request_id, action_type, action_note, action_by) VALUES (?, 'RESUBMIT', 'Resubmitted after revision', ?)")
        ->execute([$id, currentUserId()]);

    $pdo->commit();

    header('Location: detail.php?id=' . $id . '&updated=1');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[update.php] ' . $e->getMessage());
    header('Location: edit.php?id=' . $id . '&error=' . urlencode($e->getMessage()));
    exit;
}
