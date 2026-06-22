<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
require_once '../../helpers/audit.php';
requireRole(['superadmin']);

$id              = (int)($_POST['id'] ?? 0);
$name            = trim($_POST['name'] ?? '');
$username        = trim($_POST['username'] ?? '');
$email           = trim($_POST['email'] ?? '');
$role            = $_POST['role'] ?? '';
$department      = trim($_POST['department'] ?? '');
$isActive        = (int)($_POST['is_active'] ?? 1);
$password        = trim($_POST['password'] ?? '');
$passwordConfirm = $_POST['password_confirm'] ?? '';

if (!$id || !$name || !$username || !$role) {
    header('Location: edit.php?id=' . $id . '&error=' . urlencode('Semua field wajib diisi.'));
    exit;
}

if (!in_array($role, ['superadmin', 'admin', 'manager', 'qc', 'qc_prod'], true)) {
    header('Location: edit.php?id=' . $id . '&error=' . urlencode('Role tidak valid.'));
    exit;
}

$check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
$check->execute([$username, $id]);
if ($check->fetch()) {
    header('Location: edit.php?id=' . $id . '&error=' . urlencode('Username sudah digunakan.'));
    exit;
}

if ($password !== '') {
    if (strlen($password) < 6) {
        header('Location: edit.php?id=' . $id . '&error=' . urlencode('Password minimal 6 karakter.'));
        exit;
    }
    if ($password !== $passwordConfirm) {
        header('Location: edit.php?id=' . $id . '&error=' . urlencode('Password dan konfirmasi tidak cocok.'));
        exit;
    }
}

try {
    $pdo->beginTransaction();

    if ($password !== '') {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET name = ?, username = ?, email = ?, role = ?, department = ?, is_active = ?, password = ? WHERE id = ?")
            ->execute([$name, $username, $email ?: null, $role, $department ?: null, $isActive, $hashedPassword, $id]);
        writeAuditLog($pdo, 'USER_UPDATED', "Edit user: $username ($role) — termasuk reset password");
    } else {
        $pdo->prepare("UPDATE users SET name = ?, username = ?, email = ?, role = ?, department = ?, is_active = ? WHERE id = ?")
            ->execute([$name, $username, $email ?: null, $role, $department ?: null, $isActive, $id]);
        writeAuditLog($pdo, 'USER_UPDATED', "Edit user: $username ($role)");
    }

    $pdo->commit();
    header('Location: index.php?success=updated');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: edit.php?id=' . $id . '&error=' . urlencode($e->getMessage()));
    exit;
}