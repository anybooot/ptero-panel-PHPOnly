<?php

declare(strict_types=1);

/**
 * Direct public entrypoint for Pterodactyl Setup Wizard.
 */
$_SERVER['REQUEST_URI'] = '/setup';
require_once __DIR__ . '/index.php';
