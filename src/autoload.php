<?php

declare(strict_types=1);

/**
 * Pure PSR-4 Autoloader for Pterodactyl and PHPMailer.
 */
spl_autoload_register(function (string $class): void {
    // 1. Pterodactyl namespace
    $prefix = 'Pterodactyl\\';
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR;
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) === 0) {
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
        return;
    }

    // 2. PHPMailer namespace
    $phpMailerPrefix = 'PHPMailer\\PHPMailer\\';
    $phpMailerLen = strlen($phpMailerPrefix);

    if (strncmp($phpMailerPrefix, $class, $phpMailerLen) === 0) {
        $relativeClass = substr($class, $phpMailerLen);
        $file = $baseDir . 'PHPMailer' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
        return;
    }
});
