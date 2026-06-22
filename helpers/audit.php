<?php
function writeAuditLog(
    PDO $pdo,
    string $action,
    string $description = '',
    ?int $userId = null,
    ?string $username = null,
    ?string $role = null
): void {
    try {
        // Get user info from session if not provided
        if ($userId === null && isset($_SESSION['user'])) {
            $userId = $_SESSION['user']['id'] ?? null;
            $username = $_SESSION['user']['username'] ?? null;
            $role = $_SESSION['user']['role'] ?? null;
        }

        // IP Address — handle proxy/load balancer
        $ip = $_SERVER['HTTP_CLIENT_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? 'unknown';

        // Take first IP if multiple (proxy chain)
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        // PC Name — reverse DNS lookup
        $pcName = null;
        if ($ip && $ip !== 'unknown' && $ip !== '::1' && $ip !== '127.0.0.1') {
            $resolved = @gethostbyaddr($ip);
            $pcName = ($resolved && $resolved !== $ip) ? $resolved : null;
        } elseif ($ip === '::1' || $ip === '127.0.0.1') {
            $pcName = gethostname();
        }

        // Fallback: coba ambil dari HTTP header (beberapa proxy kirim ini)
        if (!$pcName) {
            $pcName = $_SERVER['HTTP_X_FORWARDED_HOST'] 
                ?? $_SERVER['HTTP_CLIENT_HOSTNAME'] 
                ?? null;
        }

        // User Agent (browser info)
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Current URL
        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . ($_SERVER['REQUEST_URI'] ?? '');

        $pdo->prepare("
            INSERT INTO audit_logs (user_id, username, role, action, description, ip_address, user_agent, pc_name, url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$userId, $username, $role, $action, $description, $ip, $userAgent, $pcName, $url]);

    } catch (Exception $e) {
        error_log('[AuditLog] ' . $e->getMessage());
    }
}

function getClientBrowser(string $userAgent): string
{
    if (str_contains($userAgent, 'Edge'))
        return 'Microsoft Edge';
    if (str_contains($userAgent, 'Chrome'))
        return 'Chrome';
    if (str_contains($userAgent, 'Firefox'))
        return 'Firefox';
    if (str_contains($userAgent, 'Safari'))
        return 'Safari';
    if (str_contains($userAgent, 'Opera'))
        return 'Opera';
    return 'Unknown Browser';
}

function getActionBadgeColor(string $action): string
{
    return match (true) {
        str_starts_with($action, 'LOGIN') => '#2563eb',
        str_starts_with($action, 'LOGOUT') => '#6b7280',
        str_starts_with($action, 'USER_') => '#d97706',
        str_starts_with($action, 'CHANGE_') => '#7c3aed',
        str_starts_with($action, 'EXPORT_') => '#0891b2',
        str_contains($action, 'APPROVED') => '#16a34a',
        str_contains($action, 'REJECTED') => '#dc2626',
        str_contains($action, 'PASSWORD') => '#db2777',
        default => '#6b7280',
    };
}