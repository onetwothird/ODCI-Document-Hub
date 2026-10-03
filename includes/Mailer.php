<?php
/**
 * Mailer Class
 * Wrapper around PHPMailer for sending emails
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private array $config;
    private ?PHPMailer $mailer = null;

    public function __construct(array $config = null)
    {
        $this->config = $config ?? require_once __DIR__ . '/mail_config.php';
    }

    /**
     * Send an email
     * 
     * @param string $to Email address of recipient
     * @param string $subject Email subject
     * @param string $body Email body (plain text)
     * @param string|null $htmlBody Email body (HTML) - optional
     * @param array $attachments Array of attachments - optional
     * @return bool True on success, false on failure
     * @throws Exception On configuration or sending errors
     */
    public function send(string $to, string $subject, string $body, ?string $htmlBody = null, array $attachments = []): bool
    {
        // For development with 'log' driver, write to log file
        if ($this->config['driver'] === 'log') {
            return $this->logEmail($to, $subject, $body, $htmlBody);
        }

        $this->initializeMailer();

        // Set recipients
        $this->mailer->addAddress($to);

        // Set from
        $this->mailer->setFrom(
            $this->config['from']['address'],
            $this->config['from']['name']
        );

        // Set content
        $this->mailer->Subject = $subject;
        $this->mailer->Body = $htmlBody ?? $body;
        $this->mailer->AltBody = $body;

        // Add attachments
        foreach ($attachments as $attachment) {
            if (isset($attachment['path']) && file_exists($attachment['path'])) {
                $this->mailer->addAttachment(
                    $attachment['path'],
                    $attachment['name'] ?? basename($attachment['path'])
                );
            }
        }

        // Send
        if (!$this->mailer->send()) {
            throw new Exception('Mailer Error: ' . $this->mailer->ErrorInfo);
        }

        return true;
    }

    /**
     * Send HTML email with plain text alternative
     */
    public function sendHtml(string $to, string $subject, string $htmlBody, string $textBody, array $attachments = []): bool
    {
        return $this->send($to, $subject, $textBody, $htmlBody, $attachments);
    }

    /**
     * Initialize PHPMailer with configuration
     */
    private function initializeMailer(): void
    {
        if ($this->mailer !== null) {
            return;
        }

        $this->mailer = new PHPMailer(true);

        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['smtp']['host'];
            $this->mailer->Port = $this->config['smtp']['port'];

            // Authentication
            if ($this->config['smtp']['auth']) {
                $this->mailer->SMTPAuth = true;
                $this->mailer->Username = $this->config['smtp']['username'];
                $this->mailer->Password = $this->config['smtp']['password'];
            }

            // Encryption
            $encryption = strtolower($this->config['smtp']['encryption']);
            if ($encryption === 'tls') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            // For development, allow self-signed certificates
            if (getenv('ODCI_MAIL_ALLOW_SELF_SIGNED') === 'true') {
                $this->mailer->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ];
            }

            // Default settings
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->isHTML(true);
        } catch (Exception $e) {
            throw new Exception('Mailer initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Log email to file (for development)
     */
    private function logEmail(string $to, string $subject, string $body, ?string $htmlBody = null): bool
    {
        $logPath = $this->config['log']['path'];
        $logDir = dirname($logPath);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logEntry = sprintf(
            "[%s] To: %s\nSubject: %s\nBody:\n%s\n%s\n---\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $body,
            $htmlBody ? "\nHTML Body:\n" . $htmlBody : ''
        );

        return (bool)file_put_contents($logPath, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Test SMTP connection
     */
    public function testConnection(): bool
    {
        if ($this->config['driver'] === 'log') {
            return true;
        }

        $this->initializeMailer();

        try {
            return $this->mailer->smtpConnect();
        } catch (Exception $e) {
            error_log('SMTP connection test failed: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Convenience function to send password reset email
 */
function sendPasswordResetEmail(string $recipient, string $resetUrl): void
{
    $mailer = new Mailer();

    $subject = 'Reset your ODCI Document Hub password';

    $textBody = implode("\r\n", [
        'Hello,',
        '',
        'We received a request to reset the password for your ODCI Document Hub account.',
        'Use the secure link below within one hour:',
        '',
        $resetUrl,
        '',
        'If you did not request this change, you can ignore this email.',
        '',
        'CvSU Naic Campus Document Hub',
    ]);

    $htmlBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
            .container { border: 1px solid #ddd; border-radius: 8px; padding: 30px; background: #fafafa; }
            .button { display: inline-block; padding: 12px 24px; background: #0056b3; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0; }
            .footer { margin-top: 30px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="container">
            <h2>Password Reset Request</h2>
            <p>Hello,</p>
            <p>We received a request to reset the password for your ODCI Document Hub account.</p>
            <p>Use the secure link below within one hour:</p>
            <p style="text-align: center;">
                <a href="' . htmlspecialchars($resetUrl) . '" class="button">Reset Password</a>
            </p>
            <p>Or copy this link: <br><small>' . htmlspecialchars($resetUrl) . '</small></p>
            <p>If you did not request this change, you can ignore this email.</p>
            <div class="footer">
                <p>CvSU Naic Campus Document Hub</p>
            </div>
        </div>
    </body>
    </html>';

    $mailer->sendHtml($recipient, $subject, $htmlBody, $textBody);
}