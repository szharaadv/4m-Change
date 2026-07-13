<?php
$_mailConfig = (require __DIR__ . '/../config/mail.php');

define('MAIL_FROM_NAME', $_mailConfig['from_name'] ?? '4M Change System');
define('MAIL_FROM_EMAIL', $_mailConfig['from_email'] ?? '');
define('MAIL_ENABLED',    $_mailConfig['enabled'] ?? false);
define('BREVO_KEY',       $_mailConfig['api_key'] ?? '');

function sendMail(string $toEmail, string $toName, string $subject, string $body): bool
{
    if (!MAIL_ENABLED) return true;

    $payload = json_encode([
        'sender'      => ['name' => MAIL_FROM_NAME, 'email' => MAIL_FROM_EMAIL],
        'to'          => [['email' => $toEmail, 'name' => $toName]],
        'subject'     => $subject,
        'htmlContent' => $body,
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . BREVO_KEY,
            'content-type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('[Mailer] cURL Error: ' . $curlError);
        return false;
    }

    if ($httpCode === 201) return true;

    error_log('[Mailer] Failed HTTP ' . $httpCode . ': ' . $response);
    return false;
}

function mailTemplate(string $title, string $body, string $accentColor = '#D0021B'): string
{
    return "
    <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0' style='background:#f4f5f7;padding:40px 16px;font-family:Arial,Helvetica,sans-serif'>
    <tr><td align='center'>
    <table role='presentation' width='560' cellpadding='0' cellspacing='0' border='0' style='max-width:560px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden'>

        <!-- Accent strip -->
        <tr><td style='background:$accentColor;height:5px;line-height:5px;font-size:0'>&nbsp;</td></tr>

        <!-- Logo -->
        <tr><td style='padding:28px 36px 0'>
            <table role='presentation' cellpadding='0' cellspacing='0' border='0'><tr>
                <td style='background:$accentColor;width:30px;height:30px;border-radius:8px;text-align:center;vertical-align:middle;color:#ffffff;font-size:12px;font-weight:700;font-family:Arial,sans-serif'>4M</td>
                <td style='padding-left:10px;vertical-align:middle;color:#9ca3af;font-size:11px;font-weight:700;letter-spacing:0.8px;text-transform:uppercase'>4M Change System</td>
            </tr></table>
        </td></tr>

        <!-- Title -->
        <tr><td style='padding:16px 36px 0'>
            <div style='color:#111827;font-size:20px;font-weight:700;letter-spacing:-0.2px;line-height:1.3'>$title</div>
        </td></tr>

        <!-- Body -->
        <tr><td style='padding:16px 36px 32px;color:#4b5563;font-size:13.5px;line-height:1.7'>
            $body
        </td></tr>

        <!-- Footer -->
        <tr><td style='padding:20px 36px;border-top:1px solid #f0f1f3'>
            <div style='color:#c1c4cb;font-size:10px;text-align:center;line-height:1.6'>
                © 2026 Yanmar · 4M Change Quality Management System<br>
                This is an automated email. Please do not reply.
            </div>
        </td></tr>

    </table>
    </td></tr>
    </table>";
}

function mailGreeting(string $name): string
{
    return "<p style='margin:0 0 14px;color:#374151'>Dear <strong>" . htmlspecialchars($name) . "</strong>-san,</p>";
}

function mailButton(string $url, string $label, string $color = '#D0021B'): string
{
    return "
    <table role='presentation' align='left' cellpadding='0' cellspacing='0' border='0' style='margin:22px 0 4px;width:auto'>
        <tr>
            <td align='center' style='background:$color;border-radius:8px;mso-padding-alt:12px 26px'>
                <a href='$url' target='_blank' style='display:inline-block;padding:12px 26px;font-family:Arial,sans-serif;font-size:13px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:8px;white-space:nowrap'>$label</a>
            </td>
        </tr>
    </table>
    <div style='clear:both'></div>";
}

function mailInfoTable(array $rows): string
{
    $trs = '';
    foreach ($rows as $label => $value) {
        if ($value === null || $value === '') continue;
        $trs .= "<tr>
            <td style='color:#9ca3af;padding:5px 0;width:120px;font-size:12px;vertical-align:top'>$label</td>
            <td style='padding:5px 0;font-size:13px;color:#111827;font-weight:600;vertical-align:top'>$value</td>
        </tr>";
    }
    return "<table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0' style='margin:4px 0'><tr><td style='background:#f9fafb;border-radius:10px;padding:14px 16px'>
        <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'>$trs</table>
    </td></tr></table>";
}

function changeSubject(string $action, string $category, string $partName, string $changeNo): string
{
    return "[4M Change] $action — $category · $partName ($changeNo)";
}

function getManagerEmails(PDO $pdo, string $category = ''): array
{
    if ($category) {
        $stmt = $pdo->prepare("
            SELECT u.name, u.email
            FROM users u
            JOIN category_managers cm ON cm.user_id = u.id
            WHERE u.role = 'manager'
              AND u.email != ''
              AND u.is_active = 1
              AND cm.category_4m = ?
        ");
        $stmt->execute([$category]);
        $result = $stmt->fetchAll();

        if (empty($result)) {
            return $pdo->query("SELECT name, email FROM users WHERE role = 'manager' AND email != '' AND is_active = 1")->fetchAll();
        }

        return $result;
    }

    return $pdo->query("SELECT name, email FROM users WHERE role = 'manager' AND email != '' AND is_active = 1")->fetchAll();
}

function getManagerEmailsByDepartment(PDO $pdo, string $department = ''): array
{
    if ($department) {
        // Cari manager yang handle department ini
        $stmt = $pdo->prepare("
            SELECT u.name, u.email
            FROM users u
            JOIN department_managers dm ON dm.user_id = u.id
            WHERE u.role = 'manager'
              AND u.email != ''
              AND u.is_active = 1
              AND dm.department = ?
        ");
        $stmt->execute([$department]);
        $result = $stmt->fetchAll();

        // Fallback: kalau tidak ada manager assigned ke dept ini, kirim ke semua manager
        if (empty($result)) {
            error_log('[Mailer] No manager assigned for department: ' . $department . ' — fallback to all managers');
            return $pdo->query("SELECT name, email FROM users WHERE role = 'manager' AND email != '' AND is_active = 1")->fetchAll();
        }

        return $result;
    }

    return $pdo->query("SELECT name, email FROM users WHERE role = 'manager' AND email != '' AND is_active = 1")->fetchAll();
}

function getQcEmails(PDO $pdo): array
{
    return $pdo->query("SELECT name, email FROM users WHERE role = 'qc' AND email != '' AND is_active = 1")->fetchAll();
}

function getSubmitterEmail(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT name, email, department FROM users WHERE id = ? AND email != '' LIMIT 1");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}