<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Setup;

use PDO;
use Throwable;
use Pterodactyl\Core\Config;
use Pterodactyl\Core\Database;
use Pterodactyl\Core\EggSeeder;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Core\View;

/**
 * Handles Web-based Setup Wizard & Host Requirements Verification.
 */
class SetupHandler
{
    public static function isInstalled(?string $basePath = null): bool
    {
        $base = $basePath ?? dirname(__DIR__, 3);

        // 1. Check lock file
        if (file_exists($base . '/.installed')) {
            return true;
        }

        // 2. Check APP_INSTALLED in env
        if (Config::get('APP_INSTALLED') === 'true' || Config::get('APP_INSTALLED') === true) {
            return true;
        }

        // 3. Fallback: check if database is already configured and admin users exist
        try {
            $appUrl = (string)Config::get('APP_URL', '');
            $dbName = (string)Config::get('DB_DATABASE', '');
            if ($appUrl !== '' && $appUrl !== 'http://panel.example.com' && $dbName !== '' && $dbName !== 'panel_not_set') {
                Database::connect();
                $userCount = Database::queryOne("SELECT COUNT(*) as cnt FROM `users` WHERE `root_admin` = 1");
                if ($userCount && (int)$userCount['cnt'] > 0) {
                    @file_put_contents($base . '/.installed', date('c'));
                    return true;
                }
            }
        } catch (Throwable) {
            // DB not yet reachable or schema not loaded
        }

        return false;
    }

    public function show(Request $request): Response
    {
        $base = dirname(__DIR__, 3);
        if (self::isInstalled($base)) {
            return Response::redirect('/auth/login');
        }

        Session::start();

        // 1. Gather host requirements
        $checks = $this->getHostRequirements($base);

        // Determine current step
        $step = (int)($request->input('step', Session::get('setup_step', 1)));
        if (!empty(Session::get('setup_db_ready'))) {
            $step = max($step, 2);
        }

        // Auto-detect current URL
        $detectedUrl = $request->getBaseUrl();

        $error = Session::getFlash('error');
        $success = Session::getFlash('success');

        $html = View::render('setup.index', [
            'checks' => $checks,
            'allChecksPassed' => $checks['all_passed'],
            'step' => $step,
            'detectedUrl' => $detectedUrl,
            'dbReady' => !empty(Session::get('setup_db_ready')),
            'error' => $error,
            'success' => $success,
        ]);

        return Response::html($html);
    }

    public function setupDatabase(Request $request): Response
    {
        $base = dirname(__DIR__, 3);
        if (self::isInstalled($base)) {
            return Response::redirect('/auth/login');
        }

        Session::start();

        $appUrl = trim((string)$request->input('app_url', ''));
        $dbHost = trim((string)$request->input('db_host', '127.0.0.1'));
        $dbPort = (int)$request->input('db_port', 3306);
        $dbName = trim((string)$request->input('db_database', 'panel'));
        $dbUser = trim((string)$request->input('db_username', 'root'));
        $dbPass = (string)$request->input('db_password', '');

        if ($appUrl === '' || $dbHost === '' || $dbName === '' || $dbUser === '') {
            Session::flash('error', 'Please fill in all required database and URL fields.');
            return Response::redirect('/setup?step=2');
        }

        try {
            // 1. Test MySQL connection (connect without dbname first to ensure db can be created if needed)
            $pdo = new PDO("mysql:host={$dbHost};port={$dbPort}", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Connect to target database
            $dbPdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName}", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $dbPdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

            // 2. Drop all existing tables if database is already populated
            $dbPdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $tablesStmt = $dbPdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $tbl) {
                $dbPdo->exec("DROP TABLE IF EXISTS `{$tbl}`");
            }
            $dbPdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            // 3. Execute database schema
            $schemaFile = $base . '/database/schema/mysql-schema.sql';
            if (!file_exists($schemaFile)) {
                throw new \Exception("Schema file database/schema/mysql-schema.sql was not found.");
            }

            $sql = (string)file_get_contents($schemaFile);
            $dbPdo->exec($sql);

            // 4. Update .env configuration
            $this->updateEnvFile($base, [
                'APP_URL' => $appUrl,
                'DB_HOST' => $dbHost,
                'DB_PORT' => (string)$dbPort,
                'DB_DATABASE' => $dbName,
                'DB_USERNAME' => $dbUser,
                'DB_PASSWORD' => $dbPass,
            ]);

            // Reload Config
            Config::load($base);

            // 5. Seed default Minecraft eggs (Velocity and Paper)
            EggSeeder::seedDefaults();

            Session::set('setup_db_ready', true);
            Session::set('setup_step', 3);
            Session::flash('success', 'Database imported and default eggs configured successfully!');

            return Response::redirect('/setup?step=3');
        } catch (Throwable $e) {
            Session::flash('error', 'Database setup error: ' . $e->getMessage());
            return Response::redirect('/setup?step=2');
        }
    }

    public function createUser(Request $request): Response
    {
        $base = dirname(__DIR__, 3);
        if (self::isInstalled($base)) {
            return Response::redirect('/auth/login');
        }

        Session::start();

        if (empty(Session::get('setup_db_ready'))) {
            Session::flash('error', 'Please configure the database first.');
            return Response::redirect('/setup?step=2');
        }

        $nameLast = trim((string)$request->input('name_last', ''));
        $nameFirst = trim((string)$request->input('name_first', ''));
        $username = trim((string)$request->input('username', ''));
        $email = trim((string)$request->input('email', ''));
        $password = (string)$request->input('password', '');
        $passwordConfirmation = (string)$request->input('password_confirmation', '');

        if ($nameLast === '' || $nameFirst === '' || $username === '' || $email === '' || $password === '') {
            Session::flash('error', 'Please fill in all administrator account fields.');
            return Response::redirect('/setup?step=3');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Please enter a valid email address.');
            return Response::redirect('/setup?step=3');
        }

        if (strlen($password) < 8) {
            Session::flash('error', 'Password must be at least 8 characters long.');
            return Response::redirect('/setup?step=3');
        }

        if ($password !== $passwordConfirmation) {
            Session::flash('error', 'Passwords do not match.');
            return Response::redirect('/setup?step=3');
        }

        try {
            // Generate UUID v4
            $uuid = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            Database::insert('users', [
                'external_id' => null,
                'uuid' => $uuid,
                'username' => $username,
                'email' => $email,
                'name_first' => $nameFirst,
                'name_last' => $nameLast,
                'password' => $hashedPassword,
                'remember_token' => null,
                'language' => 'en',
                'root_admin' => 1,
                'use_totp' => 0,
                'totp_secret' => null,
                'totp_authenticated_at' => null,
                'gravatar' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Mark as installed
            @file_put_contents($base . '/.installed', date('c'));
            $this->updateEnvFile($base, ['APP_INSTALLED' => 'true']);

            // Clear setup session data
            Session::remove('setup_db_ready');
            Session::remove('setup_step');
            Session::flash('success', 'Setup completed successfully! Log in with your new administrator credentials.');

            return Response::redirect('/auth/login');
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to create user: ' . $e->getMessage());
            return Response::redirect('/setup?step=3');
        }
    }

    private function getHostRequirements(string $base): array
    {
        $reqs = [];
        $allPassed = true;

        // PHP Version
        $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
        $reqs[] = [
            'name' => 'PHP Version',
            'required' => '>= 8.2.0',
            'current' => PHP_VERSION,
            'passed' => $phpOk,
            'critical' => true,
        ];
        if (!$phpOk) $allPassed = false;

        // Extensions
        $extensions = [
            'curl' => ['name' => 'cURL Extension', 'desc' => 'Required for HTTP communication with Wings daemon'],
            'openssl' => ['name' => 'OpenSSL Extension', 'desc' => 'Required for secure token generation and encryption'],
            'pdo' => ['name' => 'PDO Extension', 'desc' => 'Required for database abstraction'],
            'pdo_mysql' => ['name' => 'PDO MySQL Driver', 'desc' => 'Required to connect to MySQL database'],
            'mbstring' => ['name' => 'Mbstring Extension', 'desc' => 'Required for multi-byte Unicode strings'],
            'json' => ['name' => 'JSON Extension', 'desc' => 'Required for data interchange and configurations'],
            'fileinfo' => ['name' => 'FileInfo Extension', 'desc' => 'Required for file management & MIME verification'],
            'sodium' => ['name' => 'Sodium Extension', 'desc' => 'Required for modern cryptography and secure tokens'],
        ];

        foreach ($extensions as $ext => $info) {
            $loaded = extension_loaded($ext);
            $reqs[] = [
                'name' => $info['name'],
                'required' => 'Installed & Enabled',
                'current' => $loaded ? 'Enabled' : 'Missing',
                'passed' => $loaded,
                'critical' => true,
            ];
            if (!$loaded) $allPassed = false;
        }

        // Recommended / Optional: GD or Imagick
        $hasImage = extension_loaded('gd') || extension_loaded('imagick');
        $reqs[] = [
            'name' => 'GD or Imagick Extension',
            'required' => 'Recommended',
            'current' => $hasImage ? 'Enabled' : 'Optional',
            'passed' => true,
            'critical' => false,
        ];

        // Storage / Config Writable check
        $writable = is_writable($base) || is_writable($base . '/.env');
        $reqs[] = [
            'name' => 'Configuration Write Permissions',
            'required' => 'Writable (.env / root)',
            'current' => $writable ? 'Writable' : 'Read-only',
            'passed' => $writable,
            'critical' => true,
        ];
        if (!$writable) $allPassed = false;

        return [
            'items' => $reqs,
            'all_passed' => $allPassed,
        ];
    }

    private function updateEnvFile(string $base, array $values): void
    {
        $envFile = $base . '/.env';
        if (!file_exists($envFile) && file_exists($base . '/.env.example')) {
            copy($base . '/.env.example', $envFile);
        }

        $content = file_exists($envFile) ? (string)file_get_contents($envFile) : '';

        // Ensure APP_KEY exists
        if (!preg_match('/^APP_KEY=base64:.+$/m', $content)) {
            $key = 'base64:' . base64_encode(random_bytes(32));
            $values['APP_KEY'] = $key;
        }

        foreach ($values as $k => $v) {
            if (preg_match("/^{$k}=.*$/m", $content)) {
                $content = preg_replace("/^{$k}=.*$/m", "{$k}={$v}", $content);
            } else {
                $content .= "\n{$k}={$v}";
            }
        }

        file_put_contents($envFile, trim($content) . "\n");
    }
}
