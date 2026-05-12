<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
requireRole(['superadmin']);
include '../../templates/header.php';
include '../../templates/navbar.php';

$users = $pdo->query("SELECT * FROM users ORDER BY role ASC, name ASC")->fetchAll();

$roleBadge = [
    'superadmin' => 'badge-manager',
    'manager' => 'badge-submitted',
    'qc' => 'badge-qc',
    'admin' => 'badge-draft',
];
?>

<div class="page-header">
    <div>
        <div class="page-title">User Management</div>
        <div class="page-sub">Kelola semua akun pengguna sistem</div>
    </div>
    <a href="create.php" class="btn btn-primary">+ Add User</a>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <?php
        $msg = [
            'created' => 'User berhasil ditambahkan.',
            'updated' => 'User berhasil diperbarui.',
            'deleted' => 'User berhasil dihapus.',
            'email_resent' => 'Email setup berhasil dikirim ulang.',
        ];
        echo $msg[$_GET['success']] ?? 'Berhasil.';
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger"><?= e($_GET['error']) ?></div>
<?php endif; ?>

<div class="table-wrap">
    <div class="card-header">
        Semua User <span style="color:var(--muted);font-weight:400">(<?= count($users) ?>)</span>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$users): ?>
                <tr>
                    <td colspan="7" style="text-align:center;color:var(--muted);padding:24px">Tidak ada user.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($users as $i => $u): ?>
                <tr>
                    <td style="color:var(--muted)"><?= $i + 1 ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="user-avatar" style="width:32px;height:32px;font-size:12px;flex-shrink:0">
                                <?= strtoupper(substr($u['name'], 0, 2)) ?>
                            </div>
                            <div>
                                <div style="font-weight:500;font-size:13px"><?= e($u['name']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="mono"><?= e($u['username']) ?></td>
                    <td style="color:var(--muted);font-size:12px"><?= e($u['email'] ?: '—') ?></td>
                    <td><span class="badge <?= $roleBadge[$u['role']] ?? 'badge-draft' ?>"><?= e($u['role']) ?></span></td>
                    <td>
                        <?php if ($u['is_active']): ?>
                            <span class="badge badge-closed">Active</span>
                        <?php else: ?>
                            <span class="badge badge-rejected">Pending Setup</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-2">
                            <a href="edit.php?id=<?= $u['id'] ?>" class="btn btn-sm">Edit</a>
                            <?php if (!$u['is_active'] && $u['email']): ?>
                                <form method="POST" action="resend_email.php">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm"
                                        style="background:#eff6ff;color:#2563eb;border-color:#93c5fd"
                                        onclick="return confirm('Kirim ulang email setup ke <?= e($u['name']) ?>?')">
                                        Resend Email
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ($u['id'] !== currentUserId()): ?>
                                <form method="POST" action="delete.php"
                                    onsubmit="return confirm('Hapus user <?= e($u['name']) ?>?')">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../../templates/footer.php'; ?>