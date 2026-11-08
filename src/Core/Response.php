<?php

declare(strict_types=1);

namespace Pterodactyl\Core;

/**
 * Handles HTTP responses: JSON, HTML, Redirects, File Streaming, Status codes.
 */
class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $content = '';

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public static function json(mixed $data, int $statusCode = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json; charset=utf-8';
        $content = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return new self($content ?: '{}', $statusCode, $headers);
    }

    public static function html(string $html, int $statusCode = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'text/html; charset=utf-8';
        return new self($html, $statusCode, $headers);
    }

    public static function redirect(string $url, int $statusCode = 302): self
    {
        return new self('', $statusCode, ['Location' => $url]);
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    public static function text(string $text, int $statusCode = 200): self
    {
        return new self($text, $statusCode, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        echo $this->content;
    }
}
