<?php

declare(strict_types=1);

namespace Pterodactyl\Core;

class ErrorRenderer
{
    public static function render(int $code, ?string $message = null, ?string $title = null, ?\Throwable $exception = null): string
    {
        $titles = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Access Denied',
            404 => 'Page Not Found',
            405 => 'Method Not Allowed',
            419 => 'Page Expired',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
        ];

        $defaultMessages = [
            400 => 'The request could not be understood by the server due to malformed syntax.',
            401 => 'You must be authenticated to access this page or resource.',
            403 => 'You do not have permission to access the requested resource.',
            404 => 'The page or resource you are looking for does not exist or has been moved.',
            405 => 'The requested HTTP method is not allowed for this route.',
            419 => 'Your session or CSRF token has expired. Please refresh the page and try again.',
            429 => 'You have sent too many requests in a given amount of time. Please slow down.',
            500 => 'The server encountered an unexpected error and was unable to complete your request.',
            503 => 'The server is temporarily unable to handle the request due to maintenance or overload.',
        ];

        $title = $title ?? ($titles[$code] ?? "Error {$code}");
        $message = $message ?? ($defaultMessages[$code] ?? 'An unexpected error occurred.');
        $debug = (bool)Config::get('APP_DEBUG', false);
        $appName = (string)Config::get('APP_NAME', 'Pterodactyl');

        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeAppName = htmlspecialchars($appName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $color = match ($code) {
            403, 401 => '#f59e0b', // Amber
            404 => '#3b82f6',      // Blue
            419 => '#8b5cf6',      // Purple
            default => '#ef4444',  // Red
        };

        $debugSection = '';
        if ($debug && $exception !== null) {
            $exClass = htmlspecialchars(get_class($exception), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $exMsg = htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $exFile = htmlspecialchars($exception->getFile() . ':' . $exception->getLine(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $exTrace = htmlspecialchars($exception->getTraceAsString(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $debugSection = <<<HTML
            <div style="margin-top: 24px; text-align: left; background: #0c1017; border: 1px solid #1e293b; border-radius: 8px; padding: 16px; overflow-x: auto;">
                <div style="color: #ef4444; font-weight: 700; font-family: monospace; font-size: 13px; margin-bottom: 6px;">
                    {$exClass}: {$exMsg}
                </div>
                <div style="color: #94a3b8; font-family: monospace; font-size: 12px; margin-bottom: 12px;">
                    in {$exFile}
                </div>
                <details style="color: #64748b; font-family: monospace; font-size: 11px;">
                    <summary style="cursor: pointer; color: #3b82f6; font-weight: 600; margin-bottom: 8px;">View Stack Trace</summary>
                    <pre style="margin: 0; white-space: pre-wrap; line-height: 1.5; color: #94a3b8;">{$exTrace}</pre>
                </details>
            </div>
HTML;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$code} - {$safeTitle} | {$safeAppName}</title>
    <link rel="stylesheet" href="/assets/css/pterodactyl.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
            background: radial-gradient(circle at top, #162032 0%, #0c1017 100%);
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .error-card {
            max-width: 680px;
            width: 100%;
            background: var(--bg-card, #131a26);
            border: 1px solid var(--border-color, #1f2937);
            border-radius: var(--radius-lg, 12px);
            padding: 40px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.75);
        }
        .error-code {
            font-size: 72px;
            font-weight: 900;
            line-height: 1;
            color: {$color};
            margin-bottom: 12px;
            letter-spacing: -2px;
        }
        .error-title {
            font-size: 24px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 12px;
        }
        .error-desc {
            color: var(--text-secondary, #94a3b8);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 28px;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 22px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 6px;
            border: 1px solid transparent;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease;
            font-family: inherit;
        }
        .btn-primary {
            background: #2563eb;
            color: #fff;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .btn-secondary {
            background: #1f2937;
            color: #d1d5db;
            border-color: #374151;
        }
        .btn-secondary:hover {
            background: #374151;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div style="font-size: 32px; font-weight: 800; color: #fff; margin-bottom: 24px;">
            <span style="color: #3b82f6;">▲</span> {$safeAppName}
        </div>
        <div class="error-code">{$code}</div>
        <h1 class="error-title">{$safeTitle}</h1>
        <p class="error-desc">{$safeMessage}</p>
        <div class="btn-group">
            <a href="/" class="btn btn-primary" style="padding: 10px 24px; text-decoration: none;">Return to Dashboard</a>
            <button onclick="window.history.back();" class="btn btn-secondary" style="padding: 10px 24px; cursor: pointer;">Go Back</button>
            <button onclick="window.location.reload();" class="btn btn-secondary" style="padding: 10px 20px; cursor: pointer;">Reload</button>
        </div>
        {$debugSection}
    </div>
</body>
</html>
HTML;
    }
}
