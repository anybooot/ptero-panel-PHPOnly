<?php

declare(strict_types=1);

/**
 * Direct entrypoint for Pterodactyl Setup Wizard.
 * Preserves query parameters across all hosting environments.
 */
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
$_SERVER['REQUEST_URI'] = '/setup' . $query;

if (file_exists(__DIR__ . '/public/index.php')) {
    require_once __DIR__ . '/public/index.php';
} else {
    require_once __DIR__ . '/index.php';
}
