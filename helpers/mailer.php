<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/src/SMTP.php';

function sendMail(string $toEmail, string $toName, string $subject, string $body): bool
{
    $config = require __DIR__ . '/../config/mail.php';

    if (!$config['enabled']) return true;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $config['port'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('[Mailer] ' . $mail->ErrorInfo);
        return false;
    }
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