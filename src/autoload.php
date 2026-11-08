<?php

declare(strict_types=1);

/**
 * Pure PSR-4 Autoloader for Pterodactyl.
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'Pterodactyl\\';
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR;
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
