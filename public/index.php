<?php

declare(strict_types=1);

/**
 * Pterodactyl Panel - Native PHP 8.2+ Architecture
 * Free of Laravel, React, Vue, TypeScript, Webpack, Vite, and external frameworks.
 */

define('PTERODACTYL_START', microtime(true));

// Register PSR-4 Autoloader
require_once __DIR__ . '/../src/autoload.php';

use Pterodactyl\Core\Application;
use Pterodactyl\Core\Request;

// Instantiate Application Kernel
$app = new Application(dirname(__DIR__));

// Capture incoming HTTP request and dispatch
$request = new Request();
$response = $app->handle($request);

// Send response to client
$response->send();
