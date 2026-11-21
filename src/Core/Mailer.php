<?php

declare(strict_types=1);

namespace Pterodactyl\Core;

use RuntimeException;
use Pterodactyl\Handlers\Admin\SettingsHandler;

/**
 * Pure PHP SMTP Mailer using native sockets (no external dependencies).
 * Supports PLAIN/LOGIN authentication, STARTTLS, direct SSL, and HTML MIME emails.
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
     * Send an email over SMTP.
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
        $cfg = self::getConfig();
        if (empty($cfg['host'])) {
            throw new RuntimeException('SMTP host is not configured.');
        }

        $host = $cfg['host'];
        $port = $cfg['port'] ?: 25;
        $encryption = strtolower((string)($cfg['encryption'] ?? ''));
        $timeout = 15;

        $target = ($encryption === 'ssl' ? "ssl://{$host}" : "tcp://{$host}") . ":{$port}";
        
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ]);

        $socket = @stream_socket_client($target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            throw new RuntimeException("Could not connect to SMTP server ({$target}): {$errstr} [Code {$errno}]");
        }

        stream_set_timeout($socket, $timeout);

        try {
            self::readResponse($socket, 220);

            // Send EHLO
            $localhost = $_SERVER['SERVER_NAME'] ?? 'localhost';
            self::sendCommand($socket, "EHLO {$localhost}", [250]);

            // Handle STARTTLS
            if ($encryption === 'tls') {
                self::sendCommand($socket, 'STARTTLS', [220]);
                $crypto = @stream_socket_enable_crypto(
                    $socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
                );
                if (!$crypto) {
                    throw new RuntimeException('Failed to establish TLS encryption with SMTP server.');
                }
                self::sendCommand($socket, "EHLO {$localhost}", [250]);
            }

            // Authenticate if credentials provided
            if (!empty($cfg['username'])) {
                self::sendCommand($socket, 'AUTH LOGIN', [334]);
                self::sendCommand($socket, base64_encode($cfg['username']), [334]);
                self::sendCommand($socket, base64_encode($cfg['password']), [235]);
            }

            $fromEmail = !empty($cfg['from_address']) ? $cfg['from_address'] : 'no-reply@example.com';
            $fromName = !empty($cfg['from_name']) ? $cfg['from_name'] : 'Pterodactyl Panel';

            self::sendCommand($socket, "MAIL FROM:<{$fromEmail}>", [250]);
            self::sendCommand($socket, "RCPT TO:<{$to}>", [250, 251]);
            self::sendCommand($socket, 'DATA', [354]);

            // Build MIME message
            $boundary = '=_ptdl_' . bin2hex(random_bytes(16));
            $plainText = $altBody ?: strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));

            $headers = [];
            $headers[] = 'Date: ' . date('r');
            $headers[] = 'From: ' . self::encodeHeader($fromName) . " <{$fromEmail}>";
            $headers[] = "To: <{$to}>";
            $headers[] = 'Subject: ' . self::encodeHeader($subject);
            $headers[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . ($localhost ?: 'pterodactyl') . '>';
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
            $headers[] = 'X-Mailer: Pterodactyl Panel Native Mailer';

            $body = implode("\r\n", $headers) . "\r\n\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($plainText)) . "\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
            $body .= "--{$boundary}--\r\n";
            $body .= ".\r\n";

            fwrite($socket, $body);
            self::readResponse($socket, 250);

            self::sendCommand($socket, 'QUIT', [221]);
            fclose($socket);

            return true;
        } catch (\Throwable $e) {
            if (is_resource($socket)) {
                @fclose($socket);
            }
            throw $e;
        }
    }

    /**
     * Send a command to the socket and check expected status code(s).
     */
    private static function sendCommand($socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");
        return self::readResponse($socket, $expectedCodes);
    }

    /**
     * Read multi-line or single-line response from SMTP server.
     */
    private static function readResponse($socket, int|array $expectedCodes): string
    {
        $expectedCodes = is_array($expectedCodes) ? $expectedCodes : [$expectedCodes];
        $response = '';

        while (!feof($socket)) {
            $line = fgets($socket, 1024);
            if ($line === false) {
                break;
            }
            $response .= $line;
            // 4th character is space when it's the final line of multi-line reply
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            $cleaned = trim(preg_replace('/\r?\n/', ' ', $response));
            throw new RuntimeException("SMTP error ({$code}): {$cleaned}");
        }

        return $response;
    }

    /**
     * Encode header value according to RFC 2047.
     */
    private static function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }
}
