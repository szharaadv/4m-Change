<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
require_once '../../helpers/permissions.php';
require_once '../../helpers/audit.php';

requireRole([ROLE_SUPERADMIN]);
verifyCsrf();

$submitted = $_POST['perms'] ?? [];

try {
    $pdo->beginTransaction();

    // Rebuild the matrix for the managed roles only. The superadmin
    // is never stored — it always bypasses the table.
    $insert = $pdo->prepare("INSERT IGNORE INTO role_permissions (role, permission) VALUES (?, ?)");

    foreach (MANAGED_ROLES as $role) {
        // Clear this role's current grants.
        $del = $pdo->prepare("DELETE FROM role_permissions WHERE role = ?");
        $del->execute([$role]);

        $granted = $submitted[$role] ?? [];
        if (!is_array($granted)) {
            continue;
        }
        foreach ($granted as $perm) {
            // Only accept permissions from the known catalog.
            if (array_key_exists($perm, PERMISSIONS)) {
                $insert->execute([$role, $perm]);
            }
        }
    }

    $pdo->commit();
    writeAuditLog($pdo, 'ROLE_PERMISSIONS_UPDATED', 'Updated role permission matrix');
    header('Location: index.php?success=1');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: index.php?error=' . urlencode($e->getMessage()));
    exit;
}
