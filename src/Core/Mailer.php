<?php

declare(strict_types=1);

namespace Pterodactyl\Core;

use RuntimeException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Pterodactyl\Handlers\Admin\SettingsHandler;

/**
 * Mailer service for Pterodactyl Panel powered by PHPMailer and native SMTP.
 */
class Mailer
{
    /**
     * Get SMTP configuration options from DB settings, falling back to Config/.env.
     */
    public static function getConfig(): array
    {
        return [
            'host' => (string)SettingsHandler::getSetting('settings::mail:host', Config::get('MAIL_HOST', '')),
            'port' => (int)SettingsHandler::getSetting('settings::mail:port', Config::get('MAIL_PORT', 25)),
            'username' => (string)SettingsHandler::getSetting('settings::mail:username', Config::get('MAIL_USERNAME', '')),
            'password' => (string)SettingsHandler::getSetting('settings::mail:password', Config::get('MAIL_PASSWORD', '')),
            'encryption' => (string)SettingsHandler::getSetting('settings::mail:encryption', Config::get('MAIL_ENCRYPTION', 'tls')),
            'from_address' => (string)SettingsHandler::getSetting('settings::mail:from:address', Config::get('MAIL_FROM_ADDRESS', 'no-reply@example.com')),
            'from_name' => (string)SettingsHandler::getSetting('settings::mail:from:name', Config::get('MAIL_FROM_NAME', Config::get('APP_NAME', 'Pterodactyl Panel'))),
        ];
    }

    /**
     * Check if SMTP mailer is configured.
     */
    public static function isConfigured(): bool
    {
        $cfg = self::getConfig();
        return !empty($cfg['host']) && $cfg['host'] !== 'smtp.example.com';
    }

    /**
     * Instantiate and configure a PHPMailer object with panel settings.
     */
    public static function createPHPMailer(): PHPMailer
    {
        $cfg = self::getConfig();
        if (empty($cfg['host'])) {
            throw new RuntimeException('SMTP host is not configured.');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $cfg['host'];
        $mail->Port = $cfg['port'] ?: 25;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Timeout = 15;

        $enc = strtolower((string)($cfg['encryption'] ?? ''));
        if ($enc === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPAutoTLS = true;
        } elseif ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->SMTPAutoTLS = false;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        if (!empty($cfg['username'])) {
            $mail->SMTPAuth = true;
            $mail->Username = $cfg['username'];
            $mail->Password = $cfg['password'] ?? '';
        } else {
            $mail->SMTPAuth = false;
        }

        // Permissive SSL stream options for internal/self-signed/development mail servers
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ];

        $fromAddress = !empty($cfg['from_address']) ? $cfg['from_address'] : 'no-reply@example.com';
        $fromName = !empty($cfg['from_name']) ? $cfg['from_name'] : Config::get('APP_NAME', 'Pterodactyl Panel');
        $mail->setFrom($fromAddress, $fromName);

        return $mail;
    }

    /**
     * Send an email over SMTP using PHPMailer.
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $htmlBody HTML content
     * @param string|null $altBody Optional plain-text fallback
     * @return bool
     * @throws RuntimeException
     */
    public static function send(string $to, string $subject, string $htmlBody, ?string $altBody = null): bool
    {
        try {
            $mail = self::createPHPMailer();
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $altBody ?: strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));

            return $mail->send();
        } catch (PHPMailerException $e) {
            throw new RuntimeException('PHPMailer error: ' . $e->getMessage(), (int)$e->getCode(), $e);
        } catch (\Throwable $e) {
            throw new RuntimeException('Mail delivery error: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }
}
