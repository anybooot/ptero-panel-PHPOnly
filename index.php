<?php

declare(strict_types=1);

/**
 * Pterodactyl Panel - Root Entry Point
 * Ensures 100% universal compatibility across all hosting environments:
 * cPanel, shared hosts, free PHP hosts (InfinityFree, ByetHost, etc.), VPS, XAMPP, Nginx, Apache, LiteSpeed.
 */

// Handle PHP built-in web server static files (php -S)
if (php_sapi_name() === 'cli-server') {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $publicFile = __DIR__ . '/public' . $uriPath;
    if ($uriPath !== '/' && file_exists($publicFile) && !is_dir($publicFile)) {
        return false;
    }
}

// Direct static asset serving fallback when mod_rewrite is disabled or bypassed on shared/free hosts
$reqUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
if ($reqUri !== '' && $reqUri !== '/' && !str_ends_with($reqUri, '.php')) {
    $cleanPath = '/' . ltrim(preg_replace('#^/public#', '', $reqUri), '/');
    if (!str_contains($cleanPath, '..')) {
        $candidates = [
            __DIR__ . $cleanPath,
            __DIR__ . '/public' . $cleanPath,
        ];
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
                $mimeTypes = [
                    'css'   => 'text/css; charset=utf-8',
                    'js'    => 'application/javascript; charset=utf-8',
                    'svg'   => 'image/svg+xml',
                    'png'   => 'image/png',
                    'jpg'   => 'image/jpeg',
                    'jpeg'  => 'image/jpeg',
                    'gif'   => 'image/gif',
                    'ico'   => 'image/x-icon',
                    'woff'  => 'font/woff',
                    'woff2' => 'font/woff2',
                    'ttf'   => 'font/ttf',
                    'json'  => 'application/json',
                    'map'   => 'application/json',
                ];
                header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
                header('Content-Length: ' . filesize($candidate));
                header('Cache-Control: public, max-age=86400');
                readfile($candidate);
                exit;
            }
        }
    }
}

// Forward to public front controller
require_once __DIR__ . '/public/index.php';
