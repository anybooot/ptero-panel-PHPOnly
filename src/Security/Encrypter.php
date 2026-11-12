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
            // Auto-generate key if missing
            $generatedKey = 'base64:' . base64_encode(random_bytes(32));
            $envPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
            if (!file_exists($envPath)) {
                $example = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env.example';
                if (file_exists($example)) {
                    @copy($example, $envPath);
                }
            }
            if (file_exists($envPath)) {
                $envContent = (string)file_get_contents($envPath);
                if (preg_match('/^APP_KEY=.*$/m', $envContent)) {
                    $envContent = preg_replace('/^APP_KEY=.*$/m', "APP_KEY={$generatedKey}", $envContent);
                } else {
                    $envContent .= "\nAPP_KEY={$generatedKey}\n";
                }
                @file_put_contents($envPath, $envContent);
            }
            Config::set('APP_KEY', $generatedKey);
            $appKey = $generatedKey;
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
        $payload = $serialize ? serialize($value) : (string)$value;

        if (function_exists('openssl_cipher_iv_length') && function_exists('openssl_encrypt')) {
            $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
            $encrypted = openssl_encrypt($payload, self::CIPHER, $key, 0, $iv);

            if ($encrypted === false) {
                throw new RuntimeException('Could not encrypt the data with OpenSSL.');
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

        // Fallback using sodium secretbox if OpenSSL is not installed
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $sodiumKey = hash('sha256', $key, true);
            $ciphertext = sodium_crypto_secretbox($payload, $nonce, $sodiumKey);
            return base64_encode(json_encode([
                'driver' => 'sodium',
                'nonce' => base64_encode($nonce),
                'value' => base64_encode($ciphertext),
            ]));
        }

        // Bare fallback (obfuscation when no crypto ext is loaded)
        return 'raw:' . base64_encode($payload);
    }

    public static function decrypt(string $payload, bool $unserialize = true): mixed
    {
        if (str_starts_with($payload, 'raw:')) {
            $raw = base64_decode(substr($payload, 4), true);
            if ($unserialize && $raw !== false) {
                $unserialized = @unserialize($raw);
                return $unserialized !== false ? $unserialized : $raw;
            }
            return $raw;
        }

        $decodedJson = base64_decode($payload, true);
        if ($decodedJson === false) {
            return $payload;
        }

        $data = json_decode($decodedJson, true);
        if (!is_array($data)) {
            return $payload;
        }

        // Check if sodium driver
        if (isset($data['driver']) && $data['driver'] === 'sodium' && function_exists('sodium_crypto_secretbox_open')) {
            $key = self::getKey();
            $sodiumKey = hash('sha256', $key, true);
            $nonce = base64_decode($data['nonce'] ?? '', true);
            $ciphertext = base64_decode($data['value'] ?? '', true);
            if ($nonce && $ciphertext) {
                $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $sodiumKey);
                if ($decrypted !== false) {
                    if ($unserialize) {
                        $unserialized = @unserialize($decrypted);
                        if ($unserialized !== false) return $unserialized;
                    }
                    return $decrypted;
                }
            }
        }

        if (!isset($data['iv'], $data['value'], $data['mac'])) {
            return $payload;
        }

        $key = self::getKey();
        $calcMac = hash_hmac('sha256', $data['iv'] . $data['value'], $key);
        if (!hash_equals($calcMac, $data['mac'])) {
            throw new RuntimeException('MAC validation failed on decrypted payload.');
        }

        $iv = base64_decode($data['iv'], true);
        if (!function_exists('openssl_decrypt')) {
            throw new RuntimeException('OpenSSL PHP extension is required to decrypt this data.');
        }

        $decrypted = openssl_decrypt($data['value'], self::CIPHER, $key, 0, $iv);

        if ($decrypted === false) {
            throw new RuntimeException('Could not decrypt the data.');
        }

        if ($unserialize || (is_string($decrypted) && preg_match('/^[aOsbid]:/', $decrypted))) {
            $unserialized = @unserialize($decrypted);
            if ($unserialized !== false || $decrypted === 'b:0;') {
                return $unserialized;
            }
        }

        return $decrypted;
    }

    public static function encryptString(string $value): string
    {
        return self::encrypt($value, false);
    }

    public static function decryptString(string $payload): string
    {
        return (string)self::decrypt($payload, false);
    }
}
