<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/paths.php';

function getUserRole() {
    return $_SESSION['user']['role'] ?? 'admin';
}

function isManager() {
    return in_array(getUserRole(), ['manager']);
}
function isLoggedIn(): bool
{
    return isset($_SESSION['user']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . navLink('modules/auth/login.php'));
        exit;
    }
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function currentUserId(): ?int
{
    return $_SESSION['user']['id'] ?? null;
}

function currentUserRole(): ?string
{
    return $_SESSION['user']['role'] ?? null;
}

function requireRole(array $roles): void
{
    requireLogin();
    if (!in_array(currentUserRole(), $roles, true)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center">
            <h2>403 — Access Denied</h2>
            <p>You do not have permission to access this page.</p>
            <a href="' . navLink('modules/dashboard/index.php') . '">Back to Dashboard</a>
        </div>');
    }
}
