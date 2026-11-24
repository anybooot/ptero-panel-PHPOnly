<?php

declare(strict_types=1);

namespace Pterodactyl\Security;

/**
 * High-performance, atomic Rate Limiter.
 * Works seamlessly across all hosting environments with zero external dependencies.
 */
class RateLimiter
{
    private static ?string $storageDir = null;

    private static function getStorageDir(): string
    {
        if (self::$storageDir !== null) {
            return self::$storageDir;
        }

        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pterodactyl_ratelimits';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        self::$storageDir = $dir;
        return self::$storageDir;
    }

    private static function getFilePath(string $key): string
    {
        $hash = hash('sha256', $key);
        return self::getStorageDir() . DIRECTORY_SEPARATOR . "rl_{$hash}.json";
    }

    /**
     * Determine if the given key has been "accessed" too many times.
     */
    public static function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $data = self::read($key);
        if ($data === null) {
            return false;
        }

        $now = time();
        if ($now >= $data['expires_at']) {
            self::clear($key);
            return false;
        }

        return $data['attempts'] >= $maxAttempts;
    }

    /**
     * Increment the counter for a given key for a given decay time.
     */
    public static function hit(string $key, int $decaySeconds = 60): int
    {
        $file = self::getFilePath($key);
        $fp = @fopen($file, 'c+');
        if (!$fp) {
            return 1;
        }

        @flock($fp, LOCK_EX);
        $content = stream_get_contents($fp);
        $now = time();

        $data = null;
        if (!empty($content)) {
            $decoded = json_decode($content, true);
            if (is_array($decoded) && isset($decoded['expires_at'], $decoded['attempts'])) {
                if ($now < $decoded['expires_at']) {
                    $data = $decoded;
                }
            }
        }

        if ($data === null) {
            $data = [
                'attempts' => 1,
                'expires_at' => $now + $decaySeconds,
                'reset_at' => $now + $decaySeconds,
            ];
        } else {
            $data['attempts']++;
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        fflush($fp);
        @flock($fp, LOCK_UN);
        fclose($fp);

        // Periodically prune stale files
        if (mt_rand(1, 100) === 1) {
            self::pruneStale();
        }

        return $data['attempts'];
    }

    /**
     * Get the number of attempts for the given key.
     */
    public static function attempts(string $key): int
    {
        $data = self::read($key);
        if ($data === null || time() >= $data['expires_at']) {
            return 0;
        }

        return (int)$data['attempts'];
    }

    /**
     * Get the number of remaining attempts for the given key.
     */
    public static function remaining(string $key, int $maxAttempts): int
    {
        $attempts = self::attempts($key);
        return max(0, $maxAttempts - $attempts);
    }

    /**
     * Get the number of seconds until the "key" is accessible again.
     */
    public static function availableIn(string $key): int
    {
        $data = self::read($key);
        if ($data === null) {
            return 0;
        }

        $now = time();
        return max(0, (int)$data['expires_at'] - $now);
    }

    /**
     * Clear the hits and lockout for the key.
     */
    public static function clear(string $key): void
    {
        $file = self::getFilePath($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    private static function read(string $key): ?array
    {
        $file = self::getFilePath($key);
        if (!file_exists($file)) {
            return null;
        }

        $content = @file_get_contents($file);
        if ($content === false || $content === '') {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['expires_at'], $data['attempts'])) {
            return null;
        }

        return $data;
    }

    private static function pruneStale(): void
    {
        $dir = self::getStorageDir();
        $files = @glob($dir . DIRECTORY_SEPARATOR . 'rl_*.json');
        if (!is_array($files)) {
            return;
        }

        $now = time();
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content) {
                $data = json_decode($content, true);
                if (is_array($data) && isset($data['expires_at']) && $now >= $data['expires_at']) {
                    @unlink($file);
                }
            }
        }
    }
}
