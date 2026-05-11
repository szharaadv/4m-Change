<?php
$_mailConfig = (require __DIR__ . '/../config/mail.php');

define('MAIL_FROM_NAME', $_mailConfig['from_name'] ?? '4M Change System');
define('MAIL_FROM_EMAIL', $_mailConfig['from_email'] ?? '');
define('MAIL_ENABLED', $_mailConfig['enabled'] ?? false);
define('BREVO_KEY', $_mailConfig['api_key'] ?? '');

function sendMail(string $toEmail, string $toName, string $subject, string $body): bool
{
    if (!MAIL_ENABLED)
        return true;

    $payload = json_encode([
        'sender' => ['name' => MAIL_FROM_NAME, 'email' => MAIL_FROM_EMAIL],
        'to' => [['email' => $toEmail, 'name' => $toName]],
        'subject' => $subject,
        'htmlContent' => $body,
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'api-key: ' . BREVO_KEY,
            'content-type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('[Mailer] cURL Error: ' . $curlError);
        return false;
    }

    if ($httpCode === 201)
        return true;

    error_log('[Mailer] Failed HTTP ' . $httpCode . ': ' . $response);
    return false;
}

function mailTemplate(string $title, string $body): string
{
    return "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff;border:1px solid #e5e5e5;border-radius:8px;overflow:hidden'>
        <div style='background:#1a1a1a;padding:20px 28px;display:flex;align-items:center;gap:12px'>
            <div style='background:#D0021B;width:32px;height:32px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px'>4M</div>
            <span style='color:#fff;font-size:15px;font-weight:600;margin-left:10px'>4M Change System</span>
        </div>
        <div style='padding:28px'>
            <h2 style='color:#1a1a1a;font-size:16px;margin:0 0 16px'>$title</h2>
            $body
            <hr style='border:none;border-top:1px solid #f0f0f0;margin:24px 0'>
            <p style='color:#888;font-size:11px;margin:0'>Email ini dikirim otomatis oleh sistem 4M Change. Jangan reply email ini.</p>
        </div>
    </div>";
}

function getManagerEmails(PDO $pdo): array
{
    return $pdo->query("SELECT name, email FROM users WHERE role = 'manager' AND email != ''")->fetchAll();
}

function getQcEmails(PDO $pdo): array
{
    return $pdo->query("SELECT name, email FROM users WHERE role = 'qc' AND email != ''")->fetchAll();
}

function getSubmitterEmail(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ? AND email != '' LIMIT 1");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}