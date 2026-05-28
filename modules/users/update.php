<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
require_once '../../helpers/audit.php';
requireRole(['admin', 'superadmin']);

$id              = (int)($_POST['id'] ?? 0);
$name            = trim($_POST['name'] ?? '');
$username        = trim($_POST['username'] ?? '');
$email           = trim($_POST['email'] ?? '');
$role            = $_POST['role'] ?? '';
$password        = trim($_POST['password'] ?? '');
$passwordConfirm = $_POST['password_confirm'] ?? '';
$categories      = $_POST['categories'] ?? [];

if (!$id || !$name || !$username || !$role) {
    header('Location: edit.php?id=' . $id . '&error=' . urlencode('Semua field wajib diisi.'));
    exit;
}

if (!in_array($role, ['admin', 'manager', 'qc'], true)) {
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

// Validate categories
$validCats = ['Man', 'Material', 'Method', 'Machine'];
$categories = array_filter($categories, fn($c) => in_array($c, $validCats, true));

try {
    $pdo->beginTransaction();

    if ($password !== '') {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET name = ?, username = ?, email = ?, role = ?, password = ? WHERE id = ?")
            ->execute([$name, $username, $email ?: null, $role, $hashedPassword, $id]);
        writeAuditLog($pdo, 'USER_UPDATED', "Edit user: $username ($role) — termasuk reset password");
    } else {
        $pdo->prepare("UPDATE users SET name = ?, username = ?, email = ?, role = ? WHERE id = ?")
            ->execute([$name, $username, $email ?: null, $role, $id]);
        writeAuditLog($pdo, 'USER_UPDATED', "Edit user: $username ($role)");
    }

    // Update category assignment
    // Delete existing first
    $pdo->prepare("DELETE FROM category_managers WHERE user_id = ?")->execute([$id]);

    // Insert new categories (only if role is manager)
    if ($role === 'manager' && !empty($categories)) {
        $insertCat = $pdo->prepare("INSERT INTO category_managers (category_4m, user_id) VALUES (?, ?)");
        foreach ($categories as $cat) {
            $insertCat->execute([$cat, $id]);
        }
        writeAuditLog($pdo, 'USER_UPDATED', "Update kategori manager $username: " . implode(', ', $categories));
    }

    $pdo->commit();
    header('Location: index.php?success=updated');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: edit.php?id=' . $id . '&error=' . urlencode($e->getMessage()));
    exit;
}