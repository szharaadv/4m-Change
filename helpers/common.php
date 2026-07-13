<?php
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function generateChangeNo(PDO $pdo): string
{
    $year = date('Y');
    $last = $pdo->prepare("SELECT change_no FROM change_requests WHERE change_no LIKE ? ORDER BY id DESC LIMIT 1");
    $last->execute(["4M-$year-%"]);
    $row  = $last->fetchColumn();
    $seq  = ($row && preg_match('/4M-\d{4}-(\d+)/', $row, $m)) ? (int)$m[1] + 1 : 1;
    return sprintf('4M-%s-%04d', $year, $seq);
}

function ensureUploadPath(string $path): void
{
    if (!is_dir($path)) {
        if (!mkdir($path, 0777, true) && !is_dir($path)) {
            throw new Exception('Failed to create folder: ' . $path);
        }
    }
}

function uploadFileSingle(?array $file, string $folder, array $allowedExt, int $maxSize = 10485760): array
{
    if (!isset($file) || !is_array($file)) {
        return ['success' => false, 'message' => 'File not found'];
    }
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'message' => 'No file selected'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error: ' . $file['error']];
    }
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File size is too large. Maximum 5 MB'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return ['success' => false, 'message' => 'File format not allowed: .' . $ext];
    }

    $safeName   = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
    $newName    = uniqid('', true) . '_' . $safeName;
    $targetDir  = realpath(__DIR__ . '/../assets') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR;

    try {
        ensureUploadPath($targetDir);
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }

    if (!is_writable($targetDir)) {
        return ['success' => false, 'message' => 'Upload folder is not writable'];
    }

    if (!move_uploaded_file($file['tmp_name'], $targetDir . $newName)) {
        return ['success' => false, 'message' => 'Failed to move file'];
    }

    return [
        'success'   => true,
        'file_name' => $newName,
        'file_path' => 'assets/uploads/' . $folder . '/' . $newName,
        'file_type' => $ext,
    ];
}

function workflowBadgeClass(string $status): string
{
    return match ($status) {
        'Draft'            => 'secondary',
        'Submitted'        => 'primary',
        'Manager Approved' => 'info',
        'QC Approved'      => 'warning',
        'Closed'           => 'success',
        'Rejected'         => 'danger',
        default            => 'secondary',
    };
}

function judgeBadgeClass(string $judge): string
{
    return match ($judge) {
        'OK'      => 'success',
        'NG'      => 'danger',
        'Pending' => 'secondary',
        default   => 'secondary',
    };
}

function canApprove(string $role, string $workflowStatus, PDO $pdo = null, int $userId = 0, int $changeId = 0): bool
{
    if ($role === 'manager' && $workflowStatus === 'Submitted') {
        if ($pdo && $userId && $changeId) {
            $stmt = $pdo->prepare("
                SELECT 1 FROM change_requests cr
                JOIN users u ON cr.created_by = u.id
                JOIN department_managers dm ON dm.department = u.department AND dm.manager_id = ?
                WHERE cr.id = ?
                LIMIT 1
            ");
            $stmt->execute([$userId, $changeId]);
            return (bool)$stmt->fetch();
        }
        return false;
    }
    if ($role === 'qc' && $workflowStatus === 'Manager Approved') {
        if ($pdo && $userId && $changeId) {
            $stmt = $pdo->prepare("
                SELECT 1 FROM change_requests cr
                JOIN users u ON cr.created_by = u.id
                JOIN department_qc dq ON dq.department = u.department AND dq.qc_id = ?
                WHERE cr.id = ?
                LIMIT 1
            ");
            $stmt->execute([$userId, $changeId]);
            return (bool)$stmt->fetch();
        }
        return false;
    }
    return false;
}

function getApprovalStep(string $workflowStatus): string
{
    return match ($workflowStatus) {
        'Submitted'        => 'manager',
        'Manager Approved' => 'qc',
        'QC Approved'      => 'qc_final',
        default            => '',
    };
}

function stepLabel(string $step): string
{
    return match ($step) {
        'manager'  => 'Manager Department',
        'qc'       => 'QC',
        'qc_final' => 'QC — Final Submit',
        default    => $step,
    };
}

function getNeedCount(PDO $pdo, string $role, int $userId): int
{
    if ($role === 'manager') {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT cr.id)
            FROM change_requests cr
            JOIN users u ON cr.created_by = u.id
            JOIN department_managers dm ON dm.department = u.department AND dm.manager_id = ?
            WHERE cr.workflow_status = 'Submitted'
        ");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
    if ($role === 'qc') {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT cr.id)
            FROM change_requests cr
            JOIN users u ON cr.created_by = u.id
            JOIN department_qc dq ON dq.department = u.department AND dq.qc_id = ?
            WHERE cr.workflow_status = 'Manager Approved'
        ");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
    return 0;
}

function getPendingApprovals(PDO $pdo, string $role, int $userId, int $limit = 10): array
{
    if ($role === 'manager') {
        $stmt = $pdo->prepare("
            SELECT DISTINCT cr.id, cr.change_no, cr.category_4m, cr.part_name, cr.created_at, u.name AS submitter_name
            FROM change_requests cr
            JOIN users u ON cr.created_by = u.id
            JOIN department_managers dm ON dm.department = u.department AND dm.manager_id = ?
            WHERE cr.workflow_status = 'Submitted'
            ORDER BY cr.created_at ASC
            LIMIT $limit
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    if ($role === 'qc') {
        $stmt = $pdo->prepare("
            SELECT DISTINCT cr.id, cr.change_no, cr.category_4m, cr.part_name, cr.created_at, u.name AS submitter_name
            FROM change_requests cr
            JOIN users u ON cr.created_by = u.id
            JOIN department_qc dq ON dq.department = u.department AND dq.qc_id = ?
            WHERE cr.workflow_status = 'Manager Approved'
            ORDER BY cr.created_at ASC
            LIMIT $limit
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    return [];
}

function getDeptManagers(PDO $pdo, string $department): array
{
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email
        FROM users u
        JOIN department_managers dm ON dm.manager_id = u.id
        WHERE dm.department = ? AND u.is_active = 1
    ");
    $stmt->execute([$department]);
    return $stmt->fetchAll();
}

function getDeptQc(PDO $pdo, string $department): array
{
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email
        FROM users u
        JOIN department_qc dq ON dq.qc_id = u.id
        WHERE dq.department = ? AND u.is_active = 1
    ");
    $stmt->execute([$department]);
    return $stmt->fetchAll();
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}