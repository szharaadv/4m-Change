<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
require_once '../../helpers/audit.php';
requirePermission('users.manage');

$id = (int) ($_POST['id'] ?? 0);

if (!$id) {
    header('Location: index.php?error=' . urlencode('User not found.'));
    exit;
}

if ($id === currentUserId()) {
    header('Location: index.php?error=' . urlencode('You cannot delete your own account.'));
    exit;
}

$stmt = $pdo->prepare("SELECT id, name, username, role FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$u = $stmt->fetch();

if (!$u) {
    header('Location: index.php?error=' . urlencode('User not found.'));
    exit;
}

if ($u['role'] === 'superadmin') {
    $count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin'")->fetchColumn();
    if ($count <= 1) {
        header('Location: index.php?error=' . urlencode('Cannot delete the last remaining superadmin.'));
        exit;
    }
}

try {
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    writeAuditLog($pdo, 'USER_DELETED', "Deleted user: {$u['username']} ({$u['role']}) — {$u['name']}");
    header('Location: index.php?success=deleted');
    exit;
} catch (Exception $e) {
    header('Location: index.php?error=' . urlencode($e->getMessage()));
    exit;
}