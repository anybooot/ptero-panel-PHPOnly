<?php

declare(strict_types=1);

namespace Pterodactyl\Wings;

use RuntimeException;
use Pterodactyl\Security\Encrypter;

/**
 * Pure PHP HTTP client for communicating directly with Pterodactyl Wings Daemon over cURL.
 */
class WingsClient
{
    public static function getNodeConnectionAddress(array $node): string
    {
        $scheme = ($node['scheme'] ?? 'https') ?: 'https';
        $fqdn = $node['fqdn'] ?? '127.0.0.1';
        $port = $node['daemonListen'] ?? 8080;
        return "{$scheme}://{$fqdn}:{$port}";
    }

    public static function getNodeSecret(array $node): string
    {
        return Encrypter::decryptString($node['daemon_token']);
    }

    public static function request(
        array $node,
        string $method,
        string $endpoint,
        ?array $json = null,
        array $query = [],
        ?string $rawBody = null,
        int $timeout = 30
    ): array|string {
        $baseUrl = self::getNodeConnectionAddress($node);
        $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $secret = self::getNodeSecret($node);
        $headers = [
            "Authorization: Bearer {$secret}",
            'Accept: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Wings often uses internal/self-signed certs
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $method = strtoupper($method);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($json !== null) {
            $payload = json_encode($json, JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        } elseif ($rawBody !== null) {
            $headers[] = 'Content-Type: text/plain';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Wings Daemon connection failed: {$error}");
        }

        if ($httpCode >= 400) {
            $decoded = json_decode($response, true);
            $errMsg = $decoded['error'] ?? $decoded['message'] ?? "HTTP {$httpCode} error from Wings";
            throw new RuntimeException($errMsg, $httpCode);
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : $response;
    }

    // Power actions
    public static function sendPowerAction(array $node, string $serverUuid, string $action): void
    {
        self::request($node, 'POST', "/api/servers/{$serverUuid}/power", ['action' => $action]);
    }

    // Console commands
    public static function sendCommands(array $node, string $serverUuid, array|string $commands): void
    {
        $cmds = is_array($commands) ? $commands : [$commands];
        self::request($node, 'POST', "/api/servers/{$serverUuid}/commands", ['commands' => $cmds]);
    }

    // Files
    public static function listDirectory(array $node, string $serverUuid, string $directory): array
    {
        $res = self::request($node, 'GET', "/api/servers/{$serverUuid}/files/list-directory", null, ['directory' => $directory]);
        return is_array($res) ? $res : [];
    }

    public static function getFileContents(array $node, string $serverUuid, string $file): string
    {
        $res = self::request($node, 'GET', "/api/servers/{$serverUuid}/files/contents", null, ['file' => $file]);
        return is_string($res) ? $res : json_encode($res);
    }

    public static function writeFile(array $node, string $serverUuid, string $file, string $contents): void
    {
        self::request($node, 'POST', "/api/servers/{$serverUuid}/files/write", null, ['file' => $file], $contents);
    }

    public static function createDirectory(array $node, string $serverUuid, string $path, string $name): void
    {
        self::request($node, 'POST', "/api/servers/{$serverUuid}/files/create-directory", ['path' => $path, 'name' => $name]);
    }

    public static function renameFiles(array $node, string $serverUuid, string $root, array $files): void
    {
        self::request($node, 'PUT', "/api/servers/{$serverUuid}/files/rename", ['root' => $root, 'files' => $files]);
    }

    public static function copyFile(array $node, string $serverUuid, string $location): void
    {
        self::request($node, 'POST', "/api/servers/{$serverUuid}/files/copy", ['location' => $location]);
    }

    public static function deleteFiles(array $node, string $serverUuid, string $root, array $files): void
    {
        self::request($node, 'POST', "/api/servers/{$serverUuid}/files/delete", ['root' => $root, 'files' => $files]);
    }

    public static function compressFiles(array $node, string $serverUuid, string $root, array $files): array
    {
        $res = self::request($node, 'POST', "/api/servers/{$serverUuid}/files/compress", ['root' => $root, 'files' => $files], [], null, 900);
        return is_array($res) ? $res : [];
    }

    public static function decompressFile(array $node, string $serverUuid, string $root, string $file): void
    {
        self::request($node, 'POST', "/api/servers/{$serverUuid}/files/decompress", ['root' => $root, 'file' => $file], [], null, 900);
    }

    public static function chmodFiles(array $node, string $serverUuid, string $root, array $files): void
    {
        self::request($node, 'POST', "/api/servers/{$serverUuid}/files/chmod", ['root' => $root, 'files' => $files]);
    }

    public static function pullFile(array $node, string $serverUuid, string $url, string $directory, ?string $filename = null): void
    {
        $payload = ['url' => $url, 'root' => $directory];
        if ($filename !== null) {
            $payload['file_name'] = $filename;
        }
        self::request($node, 'POST', "/api/servers/{$serverUuid}/files/pull", $payload);
    }

    // Backups
    public static function createBackup(array $node, string $serverUuid, string $backupUuid, array $ignoredFiles = []): void
    {
        self::request($node, 'POST', "/api/servers/{$serverUuid}/backup", [
            'adapter' => 'wings',
            'uuid' => $backupUuid,
            'ignore' => implode("\n", $ignoredFiles),
        ]);
    }

    public static function restoreBackup(array $node, string $serverUuid, string $backupUuid, ?string $downloadUrl = null, bool $truncate = false): void
    {
        self::request($node, 'POST', "/api/servers/{$serverUuid}/backup/{$backupUuid}/restore", [
            'adapter' => 'wings',
            'truncate_directory' => $truncate,
            'download_url' => $downloadUrl ?? '',
        ]);
    }

    public static function deleteBackup(array $node, string $serverUuid, string $backupUuid): void
    {
        self::request($node, 'DELETE', "/api/servers/{$serverUuid}/backup/{$backupUuid}");
    }

    // Server Lifecycle
    public static function createServer(array $node, string $serverUuid, bool $startOnCompletion = true): void
    {
        self::request($node, 'POST', '/api/servers', [
            'uuid' => $serverUuid,
            'start_on_completion' => $startOnCompletion,
        ]);
    }

    public static function syncServer(array $node, string $serverUuid): void
    {
        self::request($node, 'POST', "/api/servers/{$serverUuid}/sync");
    }

    public static function deleteServer(array $node, string $serverUuid): void
    {
        self::request($node, 'DELETE', "/api/servers/{$serverUuid}");
    }

    public static function reinstallServer(array $node, string $serverUuid): void
    {
        self::request($node, 'POST', "/api/servers/{$serverUuid}/reinstall");
    }

    public static function getSystemInfo(array $node): array
    {
        $res = self::request($node, 'GET', '/api/system');
        return is_array($res) ? $res : [];
    }
}
