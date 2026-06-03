<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
require_once '../../helpers/mailer.php';
require_once '../../helpers/audit.php';
requireRole(['admin', 'manager', 'qc']);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO change_requests (
        change_no, category_4m, pic_id, part_no, implement_location, part_name, model,
        start_lot_serial, change_item, action_plan, before_desc, after_desc,
        judge_status, confirm_customer, evidence_note, workflow_status, created_by, updated_by
    ) VALUES (
        :change_no, :category_4m, :pic_id, :part_no, :implement_location, :part_name, :model,
        :start_lot_serial, :change_item, :action_plan, :before_desc, :after_desc,
        :judge_status, :confirm_customer, :evidence_note, :workflow_status, :created_by, :updated_by
    )");

    $wfStatus = $_POST['workflow_status'] ?? 'Draft';
    $changeNo = generateChangeNo($pdo);

    $stmt->execute([
        ':change_no' => $changeNo,
        ':category_4m' => $_POST['category_4m'] ?? '',
        ':pic_id' => $_POST['pic_id'] ?? currentUserId(),
        ':part_no' => trim($_POST['part_no'] ?? '') ?: null,
        ':implement_location' => trim($_POST['implement_location'] ?? ''),
        ':part_name' => trim($_POST['part_name'] ?? ''),
        ':model' => trim($_POST['model'] ?? ''),
        ':start_lot_serial' => trim($_POST['start_lot_serial'] ?? '') ?: null,
        ':change_item' => trim($_POST['change_item'] ?? ''),
        ':action_plan' => trim($_POST['action_plan'] ?? ''),
        ':before_desc' => trim($_POST['before_desc'] ?? '') ?: null,
        ':after_desc' => trim($_POST['after_desc'] ?? '') ?: null,
        ':judge_status' => $_POST['judge_status'] ?? 'Pending',
        ':confirm_customer' => $_POST['confirm_customer'] ?? 'Not Need',
        ':evidence_note' => trim($_POST['evidence_note'] ?? '') ?: null,
        ':workflow_status' => $wfStatus,
        ':created_by' => currentUserId(),
        ':updated_by' => currentUserId(),
    ]);

    $changeId = $pdo->lastInsertId();
    $partName = trim($_POST['part_name'] ?? '');
    $category = $_POST['category_4m'] ?? '';
    $detailUrl = APP_URL . "/modules/changes/detail.php?id=$changeId";
    
    // Upload before photo
    if (!empty($_FILES['before_photo']['name'])) {
        $r = uploadFileSingle($_FILES['before_photo'], 'before', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        if ($r['success']) {
            $pdo->prepare("INSERT INTO change_photos (change_request_id, photo_type, file_name, file_path) VALUES (?, 'before', ?, ?)")
                ->execute([$changeId, $r['file_name'], $r['file_path']]);
        } else {
            throw new Exception('Before photo gagal: ' . $r['message']);
        }
    }

    // Upload after photo
    if (!empty($_FILES['after_photo']['name'])) {
        $r = uploadFileSingle($_FILES['after_photo'], 'after', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        if ($r['success']) {
            $pdo->prepare("INSERT INTO change_photos (change_request_id, photo_type, file_name, file_path) VALUES (?, 'after', ?, ?)")
                ->execute([$changeId, $r['file_name'], $r['file_path']]);
        } else {
            throw new Exception('After photo gagal: ' . $r['message']);
        }
    }

    // Upload attachments
    if (!empty($_FILES['attachments']['name'][0])) {
        foreach ($_FILES['attachments']['name'] as $key => $name) {
            if ($_FILES['attachments']['error'][$key] !== UPLOAD_ERR_OK)
                continue;
            $file = [
                'name' => $name,
                'type' => $_FILES['attachments']['type'][$key],
                'tmp_name' => $_FILES['attachments']['tmp_name'][$key],
                'error' => $_FILES['attachments']['error'][$key],
                'size' => $_FILES['attachments']['size'][$key],
            ];
            $r = uploadFileSingle($file, 'attachments', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx']);
            if ($r['success']) {
                $pdo->prepare("INSERT INTO change_attachments (change_request_id, file_name, file_path, file_type) VALUES (?, ?, ?, ?)")
                    ->execute([$changeId, $r['file_name'], $r['file_path'], $r['file_type']]);
            }
        }
    }

    // History
    $pdo->prepare("INSERT INTO change_histories (change_request_id, action_type, action_note, action_by) VALUES (?, 'CREATE', ?, ?)")
        ->execute([$changeId, 'Change request dibuat — status: ' . $wfStatus, currentUserId()]);

    if ($wfStatus === 'Submitted') {
        $pdo->prepare("INSERT INTO change_histories (change_request_id, action_type, action_note, action_by) VALUES (?, 'SUBMIT', 'Submitted untuk approval', ?)")
            ->execute([$changeId, currentUserId()]);
    }

    $pdo->commit();

    // Audit log
    if ($wfStatus === 'Submitted') {
        writeAuditLog($pdo, 'CHANGE_SUBMITTED', "Submit change request $changeNo — $partName ($category)");
    } else {
        writeAuditLog($pdo, 'CHANGE_CREATED', "Buat draft change request $changeNo — $partName ($category)");
    }

    // Notifikasi email
    if ($wfStatus === 'Submitted') {
        $submitter      = getSubmitterEmail($pdo, currentUserId());
        $submitterName  = $submitter['name'] ?? 'Submitter';
        $submitterDept  = $submitter['department'] ?? '';

        // Routing: cari manager berdasarkan department submitter
        // Fallback ke category 4M jika dept tidak ada
        $mgrUsers = !empty($submitterDept)
            ? getManagerEmailsByDepartment($pdo, $submitterDept)
            : getManagerEmails($pdo, $category);

        $bodyHtml = "
            <p style='color:#444;font-size:13px;margin:0 0 16px'>Ada permohonan 4M Change baru yang membutuhkan approval <strong>Manager Department</strong>.</p>
            <table style='width:100%;border-collapse:collapse;font-size:13px'>
                <tr><td style='color:#888;padding:6px 0;width:140px'>Change No</td><td style='padding:6px 0;font-weight:600;font-family:monospace'>$changeNo</td></tr>
                <tr><td style='color:#888;padding:6px 0'>Part Name</td><td style='padding:6px 0'>$partName</td></tr>
                <tr><td style='color:#888;padding:6px 0'>Kategori</td><td style='padding:6px 0'>$category</td></tr>
                <tr><td style='color:#888;padding:6px 0'>Diajukan oleh</td><td style='padding:6px 0'>$submitterName</td></tr>
                <tr><td style='color:#888;padding:6px 0'>Department</td><td style='padding:6px 0'><?= $submitterDept ?: '—' ?></td></tr>    
                <tr><td style='color:#888;padding:6px 0'>Status</td><td style='padding:6px 0;color:#D0021B;font-weight:600'>Menunggu Approval Manager</td></tr>
            </table>
            <div style='margin:20px 0'>
                <a href='$detailUrl' style='background:#D0021B;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600'>Lihat Detail & Approve</a>
            </div>";

        foreach ($mgrUsers as $u) {
            sendMail($u['email'], $u['name'], "[4M Change] Permohonan Baru Menunggu Approval — $changeNo", mailTemplate("Permohonan Baru Masuk", $bodyHtml));
        }
    }

    header('Location: index.php?success=1');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    header('Location: create.php?error=' . urlencode($e->getMessage()));
    exit;
}