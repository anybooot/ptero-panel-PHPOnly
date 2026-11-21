<?php

declare(strict_types=1);

namespace Pterodactyl\Core;

/**
 * Encapsulates the incoming HTTP request.
 */
class Request
{
    private static string $globalBasePath = '';
    private string $method;
    private string $uri;
    private string $path;
    private string $basePath;
    private array $headers = [];
    private array $queryParams = [];
    private array $postParams = [];
    private array $jsonParams = [];
    private array $attributes = [];
    private string $rawBody;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $rawPath = parse_url($this->uri, PHP_URL_PATH) ?: '/';

        // Accurately determine base path for all hosting configurations (subfolders, vhosts, cPanel, XAMPP)
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $scriptDir = rtrim(dirname($scriptName), '/\\');
        if ($scriptDir === '/' || $scriptDir === '\\') {
            $scriptDir = '';
        }

        $basePath = '';
        if ($scriptDir !== '') {
            if (str_starts_with($rawPath, $scriptDir)) {
                $basePath = $scriptDir;
            } elseif (str_ends_with($scriptDir, '/public') && str_starts_with($rawPath, substr($scriptDir, 0, -7))) {
                $basePath = substr($scriptDir, 0, -7);
            }
        }

        $this->basePath = $basePath;
        self::$globalBasePath = $this->basePath;

        // Strip base path from routing path
        if ($this->basePath !== '' && str_starts_with($rawPath, $this->basePath)) {
            $rawPath = substr($rawPath, strlen($this->basePath));
            if (!str_starts_with($rawPath, '/')) {
                $rawPath = '/' . $rawPath;
            }
        }

        if (str_starts_with($rawPath, '/public/')) {
            $rawPath = substr($rawPath, 7);
        } elseif ($rawPath === '/public') {
            $rawPath = '/';
        }

        $this->path = $rawPath;
        $this->queryParams = $_GET;
        $this->postParams = $_POST;
        $this->rawBody = file_get_contents('php://input') ?: '';

        // Capture headers
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $this->headers[$headerName] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headerName = strtolower(str_replace('_', '-', $key));
                $this->headers[$headerName] = $value;
            }
        }

        // Method override support (_method or X-HTTP-Method-Override)
        if ($this->method === 'POST') {
            if (isset($this->postParams['_method'])) {
                $this->method = strtoupper((string)$this->postParams['_method']);
            } elseif (isset($this->headers['x-http-method-override'])) {
                $this->method = strtoupper((string)$this->headers['x-http-method-override']);
            }
        }

        // Parse JSON if Content-Type is application/json
        $contentType = $this->getHeader('content-type') ?? '';
        if (str_contains($contentType, 'application/json') && !empty($this->rawBody)) {
            $decoded = json_decode($this->rawBody, true);
            if (is_array($decoded)) {
                $this->jsonParams = $decoded;
            }
        }
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public static function getGlobalBasePath(): string
    {
        return self::$globalBasePath;
    }

    public static function setGlobalBasePath(string $basePath): void
    {
        self::$globalBasePath = $basePath;
    }

    public function getBaseUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 80) == 443
            || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
        return rtrim($protocol . $host . $this->basePath, '/');
    }

    public function getHeader(string $name): ?string
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? null;
    }

    public function getBearerToken(): ?string
    {
        $auth = $this->getHeader('authorization');
        if (!$auth && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (!$auth && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (!$auth && function_exists('getallheaders')) {
            $all = getallheaders();
            foreach ($all as $k => $v) {
                if (strtolower($k) === 'authorization') {
                    $auth = $v;
                    break;
                }
            }
        }
        if (!$auth && $this->getHeader('x-api-key')) {
            return trim((string)$this->getHeader('x-api-key'));
        }
        if ($auth) {
            $auth = trim($auth);
            if (preg_match('/Bearer\s+(\S+)/i', $auth, $matches)) {
                return $matches[1];
            }
            if (str_starts_with($auth, 'ptlc_') || str_starts_with($auth, 'ptla_') || (!str_contains($auth, ' ') && strlen($auth) > 10)) {
                return $auth;
            }
        }
        return null;
    }

    public function getClientIp(): string
    {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ipList = explode(',', $_SERVER[$key]);
                return trim($ipList[0]);
            }
        }
        return '127.0.0.1';
    }

    public function isJson(): bool
    {
        $contentType = $this->getHeader('content-type') ?? '';
        $accept = $this->getHeader('accept') ?? '';
        return str_contains($contentType, 'application/json') || str_contains($accept, 'application/json');
    }

    public function isAjax(): bool
    {
        return ($this->getHeader('x-requested-with') === 'XMLHttpRequest') || $this->isJson();
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->jsonParams[$key]
            ?? $this->postParams[$key]
            ?? $this->queryParams[$key]
            ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->queryParams, $this->postParams, $this->jsonParams);
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $val = $this->input($key, $default);
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    public function setAttribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
