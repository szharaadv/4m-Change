<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
require_once '../../helpers/mailer.php';
require_once '../../helpers/audit.php';
requirePermission('changes.create');
verifyCsrf();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO change_requests (
        change_no, category_4m, pic_id, part_no, implement_location, part_name, model,
        start_lot_serial, change_date, change_item, action_plan, before_desc, after_desc,
        judge_status, confirm_customer, evidence_note, workflow_status, created_by, updated_by, created_at, updated_at
    ) VALUES (
        :change_no, :category_4m, :pic_id, :part_no, :implement_location, :part_name, :model,
        :start_lot_serial, :change_date, :change_item, :action_plan, :before_desc, :after_desc,
        :judge_status, :confirm_customer, :evidence_note, :workflow_status, :created_by, :updated_by, :created_at, :updated_at
    )");

    $isHistorical = isset($_POST['is_historical']) && $_POST['is_historical'] == '1';
    $wfStatus = $isHistorical ? 'Closed' : ($_POST['workflow_status'] ?? 'Draft');
    $changeNo = generateChangeNo($pdo);
    $changeDate = $_POST['change_date'] ?? date('Y-m-d');

    $stmt->execute([
        ':change_no' => $changeNo,
        ':category_4m' => $_POST['category_4m'] ?? '',
        ':pic_id' => $_POST['pic_id'] ?? currentUserId(),
        ':part_no' => trim($_POST['part_no'] ?? '') ?: null,
        ':implement_location' => trim($_POST['implement_location'] ?? ''),
        ':part_name' => trim($_POST['part_name'] ?? ''),
        ':model' => trim($_POST['model'] ?? ''),
        ':start_lot_serial' => trim($_POST['start_lot_serial'] ?? '') ?: null,
        ':change_date' => $_POST['change_date'] ?? date('Y-m-d'),  // ← TAMBAH BARIS INI
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
        ':created_at' => date('Y-m-d H:i:s'),
        ':updated_at' => date('Y-m-d H:i:s'),
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
            throw new Exception('Before photo failed: ' . $r['message']);
        }
    }

    // Upload after photo
    if (!empty($_FILES['after_photo']['name'])) {
        $r = uploadFileSingle($_FILES['after_photo'], 'after', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        if ($r['success']) {
            $pdo->prepare("INSERT INTO change_photos (change_request_id, photo_type, file_name, file_path) VALUES (?, 'after', ?, ?)")
                ->execute([$changeId, $r['file_name'], $r['file_path']]);
        } else {
            throw new Exception('After photo failed: ' . $r['message']);
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
        ->execute([$changeId, 'Change request created — status: ' . $wfStatus, currentUserId()]);

    if ($wfStatus === 'Submitted') {
        $pdo->prepare("INSERT INTO change_histories (change_request_id, action_type, action_note, action_by) VALUES (?, 'SUBMIT', 'Submitted for approval', ?)")
            ->execute([$changeId, currentUserId()]);
    }

    $pdo->commit();

    // Audit log
    if ($wfStatus === 'Submitted') {
        writeAuditLog($pdo, 'CHANGE_SUBMITTED', "Submit change request $changeNo — $partName ($category)");
    } else {
        writeAuditLog($pdo, 'CHANGE_CREATED', "Created draft change request $changeNo — $partName ($category)");
    }

    // Email notification
    if ($wfStatus === 'Submitted') {
    $detailUrl     = APP_URL . "/modules/changes/detail.php?id=$changeId";
    $submitter     = getSubmitterEmail($pdo, currentUserId());
    $submitterName = $submitter['name'] ?? 'Submitter';

    // Get submitter department
    $deptStmt = $pdo->prepare("SELECT department FROM users WHERE id = ?");
    $deptStmt->execute([currentUserId()]);
    $submitterDept = $deptStmt->fetchColumn() ?? '';

    // Route to the manager of the submitter's department
    $mgrUsers = getDeptManagers($pdo, $submitterDept);

    // Fallback: if the department isn't routed yet, notify everyone
    // assigned as a manager approver in any department.
    if (empty($mgrUsers)) {
        $mgrUsers = $pdo->query("
            SELECT DISTINCT u.name, u.email
            FROM users u
            JOIN department_managers dm ON dm.manager_id = u.id
            WHERE u.is_active = 1 AND u.email IS NOT NULL AND u.email != ''
        ")->fetchAll();
    }

    $infoTable = mailInfoTable([
        'Change No'    => "<span style='font-family:monospace'>$changeNo</span>",
        'Part Name'    => $partName,
        'Category'     => $category,
        'Submitted by' => $submitterName,
        'Department'   => $submitterDept,
    ]);
    $subject = changeSubject('New Request', $category, $partName, $changeNo);

    foreach ($mgrUsers as $u) {
        $body = mailGreeting($u['name']) . "
            <p style='margin:0 0 14px'>A new 4M Change request is waiting for your approval.</p>
            $infoTable
            " . mailButton($detailUrl, 'View & Approve') . "";
        sendMail($u['email'], $u['name'], $subject, mailTemplate('New Request Submitted', $body));
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