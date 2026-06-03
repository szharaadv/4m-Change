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
            throw new Exception('Gagal membuat folder: ' . $path);
        }
    }
}

function uploadFileSingle(?array $file, string $folder, array $allowedExt, int $maxSize = 10485760): array
{
    if (!isset($file) || !is_array($file)) {
        return ['success' => false, 'message' => 'File tidak ditemukan'];
    }
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'message' => 'Tidak ada file dipilih'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error: ' . $file['error']];
    }
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 5 MB'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return ['success' => false, 'message' => 'Format file tidak diizinkan: .' . $ext];
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
        return ['success' => false, 'message' => 'Folder upload tidak writable'];
    }

    if (!move_uploaded_file($file['tmp_name'], $targetDir . $newName)) {
        return ['success' => false, 'message' => 'Gagal memindahkan file'];
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

function canApprove(string $role, string $status): bool
{
    if ($role === 'manager' && $status === 'Submitted')       return true;
    if ($role === 'qc'      && $status === 'Manager Approved') return true;
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
        return (int) $pdo->query("SELECT COUNT(*) FROM change_requests WHERE workflow_status = 'Submitted'")->fetchColumn();
    }
    if ($role === 'qc') {
        return (int) $pdo->query("SELECT COUNT(*) FROM change_requests WHERE workflow_status IN ('Manager Approved','QC Approved')")->fetchColumn();
    }
    return 0;
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