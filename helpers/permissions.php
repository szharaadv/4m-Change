<?php
// ============================================================
// Role-Based Access Control (RBAC)
// ------------------------------------------------------------
// Three roles exist in the system:
//   - superadmin : full access to everything, ALWAYS. Its
//                  permissions cannot be edited or revoked and
//                  it is the only role allowed to manage the
//                  permissions of the other roles.
//   - admin      : configurable by the superadmin.
//   - user       : configurable by the superadmin.
//
// A permission is granted to a role when a matching row exists
// in the `role_permissions` table. Absence of a row = denied.
// The superadmin bypasses this table entirely.
// ============================================================

require_once __DIR__ . '/auth.php';

// The catalog of permissions the superadmin can grant/revoke.
// key => human readable label shown in the management UI.
const PERMISSIONS = [
    'changes.view'            => 'View changes & dashboard',
    'changes.create'          => 'Create change request',
    'changes.edit'            => 'Edit / resubmit change',
    'changes.approve_manager' => 'Approve — Manager step',
    'changes.approve_qc'      => 'Approve — QC step',
    'changes.export'          => 'Export data (CSV / PDF)',
    'users.manage'            => 'Manage users',
    'routing.manage'          => 'Manage approval routing',
    'audit.view'              => 'View audit log',
];

// Roles whose permissions the superadmin can configure.
// 'superadmin' is intentionally excluded — it always has everything.
const MANAGED_ROLES = ['admin', 'user'];

const ROLE_SUPERADMIN = 'superadmin';

/**
 * Load the full role => [permission, ...] map from the database.
 * Cached for the duration of the request.
 */
function loadRolePermissions(PDO $pdo): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    foreach (MANAGED_ROLES as $r) {
        $cache[$r] = [];
    }

    try {
        $rows = $pdo->query("SELECT role, permission FROM role_permissions")->fetchAll();
        foreach ($rows as $row) {
            $cache[$row['role']][] = $row['permission'];
        }
    } catch (Throwable $e) {
        // Table missing (migration not yet run) — fail closed for
        // managed roles; superadmin still bypasses via userCan().
        error_log('[permissions] ' . $e->getMessage());
    }

    return $cache;
}

/**
 * Resolve the PDO handle: explicit argument wins, otherwise fall
 * back to the global $pdo established in config/database.php.
 */
function permissionsPdo(?PDO $pdo = null): ?PDO
{
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    return $GLOBALS['pdo'] ?? null;
}

/**
 * Does the currently logged-in user have the given permission?
 */
function userCan(string $permission, ?PDO $pdo = null): bool
{
    $role = currentUserRole();
    if ($role === null) {
        return false;
    }
    // Superadmin can do everything, always.
    if ($role === ROLE_SUPERADMIN) {
        return true;
    }

    $pdo = permissionsPdo($pdo);
    if (!$pdo) {
        return false;
    }

    $map = loadRolePermissions($pdo);
    return in_array($permission, $map[$role] ?? [], true);
}

/**
 * Guard a page: require login + a specific permission.
 */
function requirePermission(string $permission, ?PDO $pdo = null): void
{
    requireLogin();
    if (!userCan($permission, $pdo)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center">
            <h2>403 — Access Denied</h2>
            <p>You do not have permission to access this page.</p>
            <a href="' . navLink('modules/dashboard/index.php') . '">Back to Dashboard</a>
        </div>');
    }
}

/**
 * Can this user approve at either workflow step? Handy for
 * showing/hiding approver-only UI (badges, tabs, stat cards).
 */
function canApproveAny(?PDO $pdo = null): bool
{
    return userCan('changes.approve_manager', $pdo) || userCan('changes.approve_qc', $pdo);
}
