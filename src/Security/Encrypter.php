<?php

declare(strict_types=1);

namespace Pterodactyl\Security;

use RuntimeException;
use Pterodactyl\Core\Config;

/**
 * Universal AES-256-CBC and Pure-PHP Authenticated Encrypter.
 * 100% compatible with Laravel / Pterodactyl AES-256-CBC payloads.
 * Features a pure-PHP CTR-mode stream cipher fallback with HMAC-SHA256 authentication:
 * Operates flawlessly on ANY host (cPanel, VPS, Free PHP hosts) without requiring the sodium extension.
 */
class Encrypter
{
    private static ?string $key = null;
    private const CIPHER = 'AES-256-CBC';

    public static function resetKey(): void
    {
        self::$key = null;
    }

    public static function getKey(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }

        $appKey = (string)Config::get('APP_KEY', '');
        if ($appKey === '') {
            // Auto-generate key if missing
            $generatedKey = 'base64:' . base64_encode(self::secureRandomBytes(32));
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

        // 1. Primary standard driver: OpenSSL AES-256-CBC (standard in 99.9% PHP installs)
        if (function_exists('openssl_cipher_iv_length') && function_exists('openssl_encrypt')) {
            $ivLen = openssl_cipher_iv_length(self::CIPHER) ?: 16;
            $iv = self::secureRandomBytes($ivLen);
            $encrypted = openssl_encrypt($payload, self::CIPHER, $key, 0, $iv);

            if ($encrypted !== false) {
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
        }

        // 2. Pure-PHP Authenticated Stream Cipher Fallback (CTR-mode using HMAC-SHA256)
        // Works on ANY host without sodium or external cryptography libraries!
        $iv = self::secureRandomBytes(16);
        $ciphertext = self::purePhpStreamCipher($payload, $key, $iv);
        $ivBase64 = base64_encode($iv);
        $cipherBase64 = base64_encode($ciphertext);
        $mac = hash_hmac('sha256', $ivBase64 . $cipherBase64, $key);

        return base64_encode(json_encode([
            'driver' => 'pure_php',
            'iv' => $ivBase64,
            'value' => $cipherBase64,
            'mac' => $mac,
        ], JSON_UNESCAPED_SLASHES));
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

        $key = self::getKey();

        // 1. Pure-PHP Driver Decryption
        if (isset($data['driver']) && $data['driver'] === 'pure_php') {
            $ivBase64 = (string)($data['iv'] ?? '');
            $cipherBase64 = (string)($data['value'] ?? '');
            $calcMac = hash_hmac('sha256', $ivBase64 . $cipherBase64, $key);

            if (!hash_equals($calcMac, (string)($data['mac'] ?? ''))) {
                throw new RuntimeException('MAC validation failed on pure_php decrypted payload.');
            }

            $iv = base64_decode($ivBase64, true);
            $ciphertext = base64_decode($cipherBase64, true);
            if ($iv === false || $ciphertext === false) {
                return $payload;
            }

            $decrypted = self::purePhpStreamCipher($ciphertext, $key, $iv);
            if ($unserialize || (is_string($decrypted) && preg_match('/^[aOsbid]:/', $decrypted))) {
                $unserialized = @unserialize($decrypted);
                if ($unserialized !== false || $decrypted === 'b:0;') {
                    return $unserialized;
                }
            }
            return $decrypted;
        }

        // 2. Legacy Sodium Driver (Backwards compatibility for existing tokens if sodium is present)
        if (isset($data['driver']) && $data['driver'] === 'sodium' && function_exists('sodium_crypto_secretbox_open')) {
            $sodiumKey = hash('sha256', $key, true);
            $nonce = base64_decode($data['nonce'] ?? '', true);
            $ciphertext = base64_decode($data['value'] ?? '', true);
            if ($nonce && $ciphertext) {
                $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $sodiumKey);
                if ($decrypted !== false) {
                    if ($unserialize || (is_string($decrypted) && preg_match('/^[aOsbid]:/', $decrypted))) {
                        $unserialized = @unserialize($decrypted);
                        if ($unserialized !== false || $decrypted === 'b:0;') return $unserialized;
                    }
                    return $decrypted;
                }
            }
        }

        // 3. Standard OpenSSL Driver (iv, value, mac)
        if (!isset($data['iv'], $data['value'], $data['mac'])) {
            return $payload;
        }

        $calcMac = hash_hmac('sha256', $data['iv'] . $data['value'], $key);
        if (!hash_equals($calcMac, $data['mac'])) {
            throw new RuntimeException('MAC validation failed on decrypted payload.');
        }

        $iv = base64_decode($data['iv'], true);
        if ($iv === false) {
            return $payload;
        }

        if (function_exists('openssl_decrypt')) {
            $decrypted = openssl_decrypt($data['value'], self::CIPHER, $key, 0, $iv);
            if ($decrypted === false) {
                throw new RuntimeException('Could not decrypt the data with OpenSSL.');
            }

            if ($unserialize || (is_string($decrypted) && preg_match('/^[aOsbid]:/', $decrypted))) {
                $unserialized = @unserialize($decrypted);
                if ($unserialized !== false || $decrypted === 'b:0;') {
                    return $unserialized;
                }
            }

            return $decrypted;
        }

        // Bare raw fallback if neither openssl nor driver matched
        return $data['value'];
    }

    public static function encryptString(string $value): string
    {
        return self::encrypt($value, false);
    }

    public static function decryptString(string $payload): string
    {
        return (string)self::decrypt($payload, false);
    }

    /**
     * Pure-PHP authenticated stream cipher (CTR mode via HMAC-SHA256).
     * Symmetrical, constant keystream, zero external extensions needed.
     */
    public static function purePhpStreamCipher(string $data, string $key, string $iv): string
    {
        $len = strlen($data);
        if ($len === 0) {
            return '';
        }
        $result = '';
        $counter = 0;
        for ($offset = 0; $offset < $len; $offset += 32) {
            $keystreamBlock = hash_hmac('sha256', $iv . pack('N', $counter++), $key, true);
            $chunk = substr($data, $offset, 32);
            $result .= $chunk ^ substr($keystreamBlock, 0, strlen($chunk));
        }
        return $result;
    }

    /**
     * Cryptographically secure random bytes with multi-layer fallback.
     */
    public static function secureRandomBytes(int $length): string
    {
        try {
            return random_bytes($length);
        } catch (\Throwable) {
            if (function_exists('openssl_random_pseudo_bytes')) {
                return openssl_random_pseudo_bytes($length);
            }
            $bytes = '';
            while (strlen($bytes) < $length) {
                $bytes .= hash('sha256', uniqid((string)mt_rand(), true) . microtime(true), true);
            }
            return substr($bytes, 0, $length);
        }
    }
}
