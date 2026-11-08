<?php

declare(strict_types=1);

namespace Pterodactyl\Core;

/**
 * Encapsulates the incoming HTTP request.
 */
class Request
{
    private string $method;
    private string $uri;
    private string $path;
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
        $this->path = parse_url($this->uri, PHP_URL_PATH) ?: '/';
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
        if ($auth && preg_match('/Bearer\s+(\S+)/i', $auth, $matches)) {
            return $matches[1];
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
