<?php
$user = currentUser();
$role = currentUserRole();
$needCount = 0;
if ($user && isset($pdo)) {
    $needCount = getNeedCount($pdo, $role, $user['id']);
}
$currentPath = $_SERVER['PHP_SELF'];
function isActive(string $path): string
{
    global $currentPath;
    return str_contains($currentPath, $path) ? 'active' : '';
}
?>
<nav class="app-navbar">
    <div class="navbar-brand">
        <span class="brand-mark">4M</span>
        <div>
            <div class="brand-name">4M Change</div>
            <div class="brand-sub">Quality Management</div>
        </div>
    </div>

    <div class="navbar-links">
        <a href="<?= navLink('modules/dashboard/index.php') ?>" class="nav-item <?= isActive('dashboard') ?>">
            <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor">
                <path d="M1 2.5A1.5 1.5 0 012.5 1h3A1.5 1.5 0 017 2.5v3A1.5 1.5 0 015.5 7h-3A1.5 1.5 0 011 5.5v-3zm8 0A1.5 1.5 0 0110.5 1h3A1.5 1.5 0 0115 2.5v3A1.5 1.5 0 0113.5 7h-3A1.5 1.5 0 019 5.5v-3zm-8 8A1.5 1.5 0 012.5 9h3A1.5 1.5 0 017 10.5v3A1.5 1.5 0 015.5 15h-3A1.5 1.5 0 011 13.5v-3zm8 0A1.5 1.5 0 0110.5 9h3a1.5 1.5 0 011.5 1.5v3a1.5 1.5 0 01-1.5 1.5h-3A1.5 1.5 0 019 13.5v-3z"/>
            </svg>
            Dashboard
        </a>

        <a href="<?= navLink('modules/changes/index.php') ?>" class="nav-item <?= isActive('changes') ?>">
            <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor">
                <path d="M14.5 3a.5.5 0 01.5.5v9a.5.5 0 01-.5.5h-13a.5.5 0 01-.5-.5v-9a.5.5 0 01.5-.5h13zm-13-1A1.5 1.5 0 000 3.5v9A1.5 1.5 0 001.5 14h13a1.5 1.5 0 001.5-1.5v-9A1.5 1.5 0 0014.5 2h-13zM3 6.5a.5.5 0 01.5-.5h9a.5.5 0 010 1h-9a.5.5 0 01-.5-.5zm0 3a.5.5 0 01.5-.5h9a.5.5 0 010 1h-9a.5.5 0 01-.5-.5z"/>
            </svg>
            History
            <?php if ($needCount > 0): ?>
                <span class="nav-badge"><?= $needCount ?></span>
            <?php endif; ?>
        </a>

        <?php if ($user && userCan('changes.create')): ?>
        <a href="<?= navLink('modules/changes/create.php') ?>" class="nav-item <?= isActive('create') ?>">
            <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor">
                <path d="M8 2a.5.5 0 01.5.5v5h5a.5.5 0 010 1h-5v5a.5.5 0 01-1 0v-5h-5a.5.5 0 010-1h5v-5A.5.5 0 018 2z"/>
            </svg>
            + New Change
        </a>
        <?php endif; ?>

        <?php if ($user && userCan('users.manage')): ?>
        <a href="<?= navLink('modules/users/index.php') ?>" class="nav-item <?= isActive('users') ?>">
            <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor">
                <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8zm-7.978-1A.261.261 0 017 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002-.014.002H7.022zM11 7a2 2 0 100-4 2 2 0 000 4zm3-2a3 3 0 11-6 0 3 3 0 016 0zM6.936 9.28a5.88 5.88 0 00-1.23-.247A7.35 7.35 0 005 9c-4 0-5 3-5 4 0 .667.333 1 1 1h4.216A2.238 2.238 0 015 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816zM4.92 10A5.493 5.493 0 004 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275zM1.5 5.5a3 3 0 116 0 3 3 0 01-6 0zm3-2a2 2 0 100 4 2 2 0 000-4z"/>
            </svg>
            Users
        </a>
        <?php endif; ?>
        <?php if ($user && userCan('routing.manage')): ?>
        <a href="<?= navLink('modules/routing/index.php') ?>" class="nav-item <?= isActive('routing') ?>">
            <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor">
                <path d="M1 11.5a.5.5 0 00.5.5h11.793l-3.147 3.146a.5.5 0 00.708.708l4-4a.5.5 0 000-.708l-4-4a.5.5 0 00-.708.708L13.293 11H1.5a.5.5 0 00-.5.5zm14-7a.5.5 0 01-.5.5H2.707l3.147 3.146a.5.5 0 11-.708.708l-4-4a.5.5 0 010-.708l4-4a.5.5 0 11.708.708L2.707 4H14.5a.5.5 0 01.5.5z"/>
            </svg>
            Routing
        </a>
        <?php endif; ?>
        <?php if ($user && userCan('audit.view')): ?>
        <a href="<?= navLink('modules/audit/index.php') ?>" class="nav-item <?= isActive('audit') ?>">
            <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor">
                <path d="M8 1a2 2 0 012 2v4H6V3a2 2 0 012-2zm3 6V3a3 3 0 00-6 0v4a2 2 0 00-2 2v5a2 2 0 002 2h6a2 2 0 002-2V9a2 2 0 00-2-2z"/>
            </svg>
            Audit Log
        </a>
        <?php endif; ?>
        <?php if ($user && $role === 'superadmin'): ?>
        <a href="<?= navLink('modules/roles/index.php') ?>" class="nav-item <?= isActive('roles') ?>">
            <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor">
                <path d="M9.5 5.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM8 1a7 7 0 100 14A7 7 0 008 1zM4.5 7.5a3.5 3.5 0 117 0 3.5 3.5 0 01-7 0z"/>
                <path d="M8 9c-2.5 0-4 1.5-4 3v.5h8V12c0-1.5-1.5-3-4-3z"/>
            </svg>
            Roles
        </a>
        <?php endif; ?>
    </div>

    <div class="navbar-user">
        <?php if ($user): ?>
        <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr($user['name'] ?? 'U', 0, 2)) ?></div>
            <div>
                <div class="user-name"><?= e($user['name'] ?? 'User') ?></div>
                <div class="user-role"><?= e($role) ?></div>
            </div>
        </div>
        <a href="<?= navLink('modules/auth/logout.php') ?>" class="btn-logout">Logout</a>
        <?php endif; ?>
    </div>
</nav>

<div class="page-content">