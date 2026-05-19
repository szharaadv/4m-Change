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
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#ffffff'>

        <!-- Header -->
        <div style='background:#1a1a1a;padding:18px 24px;display:flex;align-items:center'>
            <div style='background:#D0021B;width:8px;height:32px;border-radius:2px;margin-right:12px'></div>
            <div>
                <div style='color:#ffffff;font-size:15px;font-weight:700;letter-spacing:0.3px'>4M Change System</div>
                <div style='color:rgba(255,255,255,0.45);font-size:10px;letter-spacing:0.5px;text-transform:uppercase'>Quality Management · Yanmar</div>
            </div>
        </div>

        <!-- Title Bar -->
        <div style='background:$accentColor;padding:12px 24px'>
            <div style='color:#ffffff;font-size:14px;font-weight:700;letter-spacing:0.2px'>$title</div>
        </div>

        <!-- Body -->
        <div style='padding:24px;background:#ffffff'>
            $body
        </div>

        <!-- Footer -->
        <div style='background:#f5f5f5;padding:12px 24px;border-top:1px solid #e5e5e5'>
            <div style='color:#aaaaaa;font-size:10px;text-align:center'>
                © 2026 Yanmar · 4M Change Quality Management System<br>
                Email ini dikirim otomatis. Jangan reply email ini.
            </div>
        </div>

    </div>";
}

function getManagerEmails(PDO $pdo, string $category = ''): array
{
    if ($category) {
        // Get managers assigned to this category
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

        // Fallback: if no manager assigned to this category, get all managers
        if (empty($result)) {
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
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ? AND email != '' LIMIT 1");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}