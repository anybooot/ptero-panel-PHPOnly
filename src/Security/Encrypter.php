<?php

declare(strict_types=1);

namespace Pterodactyl\Security;

use RuntimeException;
use Pterodactyl\Core\Config;

/**
 * 100% Laravel-compatible AES-256-CBC Encrypter.
 * Ensures existing encrypted tokens (nodes.daemon_token), passwords, and variables can be read and written seamlessly.
 */
class Encrypter
{
    private static ?string $key = null;
    private const CIPHER = 'AES-256-CBC';

    public static function getKey(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }

        $appKey = (string)Config::get('APP_KEY', '');
        if ($appKey === '') {
            throw new RuntimeException('Application encryption key (APP_KEY) is not set in environment.');
        }

        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);
            if ($decoded === false) {
                throw new RuntimeException('Invalid base64 encoding for APP_KEY.');
            }
            self::$key = $decoded;
        } else {
            self::$key = $appKey;
        }

        return self::$key;
    }

    public static function encrypt(mixed $value, bool $serialize = true): string
    {
        $key = self::getKey();
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));

        $payload = $serialize ? serialize($value) : (string)$value;
        $encrypted = openssl_encrypt($payload, self::CIPHER, $key, 0, $iv);

        if ($encrypted === false) {
            throw new RuntimeException('Could not encrypt the data.');
        }

        $ivBase64 = base64_encode($iv);
        $mac = hash_hmac('sha256', $ivBase64 . $encrypted, $key);

        $json = json_encode([
            'iv' => $ivBase64,
            'value' => $encrypted,
            'mac' => $mac,
            'tag' => '',
        ], JSON_UNESCAPED_SLASHES);

        return base64_encode($json);
    }

    public static function decrypt(string $payload, bool $unserialize = true): mixed
    {
        $key = self::getKey();
        $decodedJson = base64_decode($payload, true);
        if ($decodedJson === false) {
            // Check if string is already raw or fallback
            return $payload;
        }

        $data = json_decode($decodedJson, true);
        if (!is_array($data) || !isset($data['iv'], $data['value'], $data['mac'])) {
            return $payload;
        }

        $calcMac = hash_hmac('sha256', $data['iv'] . $data['value'], $key);
        if (!hash_equals($calcMac, $data['mac'])) {
            throw new RuntimeException('MAC validation failed on decrypted payload.');
        }

        $iv = base64_decode($data['iv'], true);
        $decrypted = openssl_decrypt($data['value'], self::CIPHER, $key, 0, $iv);

        if ($decrypted === false) {
            throw new RuntimeException('Could not decrypt the data.');
        }

        if ($unserialize) {
            $unserialized = @unserialize($decrypted);
            if ($unserialized !== false || $decrypted === 'b:0;') {
                return $unserialized;
            }
        }

        return $decrypted;
    }

    public static function encryptString(string $value): string
    {
        return self::encrypt($value, true);
    }

    public static function decryptString(string $payload): string
    {
        return (string)self::decrypt($payload, true);
    }
}
