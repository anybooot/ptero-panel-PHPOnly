<?php

declare(strict_types=1);

/**
 * Pterodactyl Panel - Root Entry Point
 * Ensures compatibility across all hosting environments (cPanel, XAMPP, VPS, Shared Hosts, Built-in PHP server).
 */

// Handle PHP built-in web server static files (php -S)
if (php_sapi_name() === 'cli-server') {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $publicFile = __DIR__ . '/public' . $uriPath;
    if ($uriPath !== '/' && file_exists($publicFile) && !is_dir($publicFile)) {
        return false;
    }
}

// Forward to public front controller
require_once __DIR__ . '/public/index.php';
