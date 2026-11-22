<?php

declare(strict_types=1);

namespace Pterodactyl\Security;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;

class ApiKeyAuth
{
    public const TYPE_CLIENT = 0;
    public const TYPE_APPLICATION = 1;

    public static function authenticate(Request $request, int $expectedType = self::TYPE_CLIENT): ?array
    {
        $bearer = trim((string)$request->getBearerToken());
        if ($bearer === '') {
            return null;
        }

        $identifier = null;
        $secret = null;

        if (str_starts_with($bearer, 'ptlc_') || str_starts_with($bearer, 'ptla_')) {
            $raw = substr($bearer, 5);
            if (str_contains($raw, '_')) {
                [$identifier, $secret] = explode('_', $raw, 2);
            } else {
                $identifier = substr($raw, 0, 16);
                $secret = substr($raw, 16);
            }
        } else {
            $identifier = substr($bearer, 0, 16);
            $secret = substr($bearer, 16);
        }

        // Try lookup by identifier first
        $key = null;
        if (!empty($identifier)) {
            if ($expectedType === self::TYPE_APPLICATION) {
                $key = Database::queryOne(
                    "SELECT * FROM `api_keys` WHERE `identifier` = :id AND `key_type` = 1 LIMIT 1",
                    ['id' => $identifier]
                );
            } else {
                $key = Database::queryOne(
                    "SELECT * FROM `api_keys` WHERE `identifier` = :id AND `key_type` IN (0, 2) LIMIT 1",
                    ['id' => $identifier]
                );
            }
        }

        // Fallback lookup by SHA256 hash of entire bearer
        if (!$key) {
            $tokenHash = hash('sha256', $bearer);
            if ($expectedType === self::TYPE_APPLICATION) {
                $key = Database::queryOne(
                    "SELECT * FROM `api_keys` WHERE `token` = :hash AND `key_type` = 1 LIMIT 1",
                    ['hash' => $tokenHash]
                );
            } else {
                $key = Database::queryOne(
                    "SELECT * FROM `api_keys` WHERE `token` = :hash AND `key_type` IN (0, 2) LIMIT 1",
                    ['hash' => $tokenHash]
                );
            }
        }

        if (!$key) {
            return null;
        }

        // Validate token secret
        $valid = false;
        try {
            $decrypted = Encrypter::decryptString($key['token']);
            if (hash_equals($decrypted, (string)$secret) || hash_equals($decrypted, $bearer) || hash_equals($decrypted, (string)$raw)) {
                $valid = true;
            }
        } catch (\Throwable) {}

        if (!$valid) {
            if (hash_equals($key['token'], hash('sha256', $bearer))) {
                $valid = true;
            } elseif ($secret !== null && hash_equals($key['token'], hash('sha256', (string)$secret))) {
                $valid = true;
            } elseif (hash_equals($key['token'], $bearer) || ($secret !== null && hash_equals($key['token'], (string)$secret)) || hash_equals($key['token'], (string)$raw)) {
                $valid = true;
            }
        }

        if (!$valid) {
            return null;
        }

        // Validate allowed IPs
        if (!empty($key['allowed_ips'])) {
            $allowed = json_decode((string)$key['allowed_ips'], true);
            if (is_array($allowed) && !empty($allowed)) {
                $clientIp = $request->getClientIp();
                if (!in_array($clientIp, $allowed, true)) {
                    return null;
                }
            }
        }

        // Update last used timestamp
        Database::execute("UPDATE `api_keys` SET `last_used_at` = NOW() WHERE `id` = :id", ['id' => $key['id']]);

        // Load associated user
        $user = Database::queryOne("SELECT * FROM `users` WHERE `id` = :id LIMIT 1", ['id' => $key['user_id']]);
        if (!$user) {
            return null;
        }

        // Ensure user is root admin for application API keys
        if ($expectedType === self::TYPE_APPLICATION && empty($user['root_admin'])) {
            return null;
        }

        $user['api_key'] = $key;
        return $user;
    }
}
