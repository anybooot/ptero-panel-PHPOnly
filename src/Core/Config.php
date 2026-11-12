<?php

declare(strict_types=1);

namespace Pterodactyl\Core;

/**
 * Handles environment variables and configuration loading.
 */
class Config
{
    private static array $env = [];
    private static bool $loaded = false;

    public static function load(string $basePath): void
    {
        if (self::$loaded) {
            return;
        }

        $envFile = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . '.env';
        if (!file_exists($envFile)) {
            $exampleFile = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . '.env.example';
            if (file_exists($exampleFile)) {
                @copy($exampleFile, $envFile);
            }
        }

        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                if (!str_contains($line, '=')) {
                    continue;
                }

                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);

                // Strip quotes if present
                if (
                    (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))
                ) {
                    $value = substr($value, 1, -1);
                }

                self::$env[$name] = $value;
                if (!array_key_exists($name, $_SERVER)) {
                    $_SERVER[$name] = $value;
                }
                if (!array_key_exists($name, $_ENV)) {
                    $_ENV[$name] = $value;
                }
            }
        }

        self::$loaded = true;
    }

    public static function set(string $key, mixed $value): void
    {
        self::$env[$key] = $value;
        $_SERVER[$key] = $value;
        $_ENV[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$env)) {
            return self::parseValue(self::$env[$key]);
        }

        $val = getenv($key);
        if ($val !== false) {
            return self::parseValue($val);
        }

        return $default;
    }

    private static function parseValue(mixed $val): mixed
    {
        if (!is_string($val)) {
            return $val;
        }

        $lower = strtolower($val);
        return match ($lower) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => $val,
        };
    }
}
