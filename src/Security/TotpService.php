<?php

declare(strict_types=1);

namespace Pterodactyl\Security;

/**
 * Pure PHP RFC 6238 TOTP (Time-based One-Time Password) service.
 * Supports Google Authenticator / 2FA without any external libraries.
 */
class TotpService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $length = 16): string
    {
        $alphabet = self::BASE32_ALPHABET;
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }
        return $secret;
    }

    public static function getCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $counter = (int)floor($timestamp / 30);
        $counterBinary = pack('J', $counter); // 64-bit big-endian unsigned integer

        $secretBinary = self::base32Decode($secret);
        $hash = hash_hmac('sha1', $counterBinary, $secretBinary, true);

        $offset = ord($hash[19]) & 0x0F;
        $binary = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $code = (string)($binary % 1000000);
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    public static function verify(string $secret, string $code, int $discrepancy = 1): bool
    {
        $code = trim($code);
        if (strlen($code) !== 6) {
            return false;
        }

        $now = time();
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calcCode = self::getCode($secret, $now + ($i * 30));
            if (hash_equals($calcCode, $code)) {
                return true;
            }
        }

        return false;
    }

    public static function getOtpAuthUrl(string $issuer, string $accountName, string $secret): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode($accountName),
            $secret,
            rawurlencode($issuer)
        );
    }

    public static function base32Decode(string $base32): string
    {
        $base32 = strtoupper(rtrim($base32, '='));
        $binary = '';
        $buffer = 0;
        $bufferBits = 0;

        $alphabet = self::BASE32_ALPHABET;
        for ($i = 0, $len = strlen($base32); $i < $len; $i++) {
            $val = strpos($alphabet, $base32[$i]);
            if ($val === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $val;
            $bufferBits += 5;

            if ($bufferBits >= 8) {
                $bufferBits -= 8;
                $binary .= chr(($buffer >> $bufferBits) & 0xFF);
            }
        }

        return $binary;
    }
}
