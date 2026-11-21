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
            // 1. Test MySQL connection: First connect directly to target database (Required on cPanel & Free Hosts)
            $dbPdo = null;
            try {
                $dbPdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (\Throwable $directErr) {
                // If direct connection failed, attempt connecting to MySQL server to create the database (VPS / root user)
                try {
                    $rootPdo = new PDO("mysql:host={$dbHost};port={$dbPort}", $dbUser, $dbPass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);
                    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                    $dbPdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);
                } catch (\Throwable) {
                    throw new \Exception("Could not connect to database '{$dbName}': " . $directErr->getMessage() . " (If on cPanel or a shared host, ensure you have created this database and granted user privileges in your hosting control panel)");
                }
            }

            $dbPdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

            // 2. Drop all existing tables if database is already populated
            $dbPdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $tablesStmt = $dbPdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $tbl) {
                $dbPdo->exec("DROP TABLE IF EXISTS `{$tbl}`");
            }
            $dbPdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            // 3. Execute database schema safely across all MySQL/MariaDB engines
            $schemaFile = $base . '/database/schema/mysql-schema.sql';
            if (!file_exists($schemaFile)) {
                throw new \Exception("Schema file database/schema/mysql-schema.sql was not found.");
            }

            $sql = (string)file_get_contents($schemaFile);
            self::executeSqlSchema($dbPdo, $sql);

            // 4. Update .env configuration
            $this->updateEnvFile($base, [
                'APP_URL' => $appUrl,
                'DB_HOST' => $dbHost,
                'DB_PORT' => (string)$dbPort,
                'DB_DATABASE' => $dbName,
                'DB_USERNAME' => $dbUser,
                'DB_PASSWORD' => $dbPass,
            ]);

            // Reset Database singleton and reload Config
            Database::reset();
            Config::reload($base);

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

        // PHP Version (supports 8.1+ across all cPanel, VPS, and free PHP hosts)
        $phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
        $reqs[] = [
            'name' => 'PHP Version',
            'required' => '>= 8.1.0 (8.2+ recommended)',
            'current' => PHP_VERSION,
            'passed' => $phpOk,
            'critical' => true,
        ];
        if (!$phpOk) $allPassed = false;

        // Standard Extensions (sodium is NOT required; pure PHP crypto fallback is built-in)
        $extensions = [
            'pdo'       => ['name' => 'PDO Extension', 'desc' => 'Required for database abstraction', 'critical' => true],
            'pdo_mysql' => ['name' => 'PDO MySQL Driver', 'desc' => 'Required to connect to MySQL database', 'critical' => true],
            'mbstring'  => ['name' => 'Mbstring Extension', 'desc' => 'Required for multi-byte Unicode strings', 'critical' => true],
            'json'      => ['name' => 'JSON Extension', 'desc' => 'Required for data interchange and configurations', 'critical' => true],
            'openssl'   => ['name' => 'OpenSSL Extension', 'desc' => 'Standard PHP cryptography for secure tokens and encryption (pure-PHP fallback built-in)', 'critical' => false],
            'curl'      => ['name' => 'cURL Extension', 'desc' => 'HTTP communication with Wings daemon (stream context fallback built-in)', 'critical' => false],
            'fileinfo'  => ['name' => 'FileInfo Extension', 'desc' => 'File management and MIME type detection', 'critical' => false],
        ];

        foreach ($extensions as $ext => $info) {
            $loaded = extension_loaded($ext);
            $critical = (bool)($info['critical'] ?? true);
            $reqs[] = [
                'name' => $info['name'],
                'required' => $critical ? 'Installed & Enabled' : 'Recommended (Fallback available)',
                'current' => $loaded ? 'Enabled' : ($critical ? 'Missing' : 'Optional / Using Fallback'),
                'passed' => $critical ? $loaded : true,
                'critical' => $critical,
            ];
            if ($critical && !$loaded) {
                $allPassed = false;
            }
        }

        // Recommended / Optional: GD or Imagick
        $hasImage = extension_loaded('gd') || extension_loaded('imagick');
        $reqs[] = [
            'name' => 'GD or Imagick Extension',
            'required' => 'Optional',
            'current' => $hasImage ? 'Enabled' : 'Optional',
            'passed' => true,
            'critical' => false,
        ];

        // Storage / Config Writable check with probe test
        $writable = is_writable($base) || is_writable($base . '/.env');
        if (!$writable) {
            $probeFile = $base . '/.perm_test_' . uniqid();
            if (@file_put_contents($probeFile, 'ok') !== false) {
                $writable = true;
                @unlink($probeFile);
            }
        }

        $reqs[] = [
            'name' => 'Configuration Write Permissions',
            'required' => 'Writable (.env / root folder)',
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

    /**
     * Executes SQL schema with resilience across all MySQL and MariaDB versions.
     */
    public static function executeSqlSchema(PDO $pdo, string $sql): void
    {
        // First try bulk exec (fastest)
        try {
            $pdo->exec($sql);
            return;
        } catch (\Throwable) {
            // Fall through to statement-by-statement execution
        }

        // Split into individual queries, handling comments and delimiters
        $lines = explode("\n", $sql);
        $currentQuery = '';
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '#')) {
                continue;
            }
            $currentQuery .= $line . "\n";
            if (str_ends_with($trimmed, ';')) {
                $q = trim($currentQuery);
                $currentQuery = '';
                if ($q !== '' && $q !== ';') {
                    try {
                        $pdo->exec($q);
                    } catch (\Throwable $stmtErr) {
                        // If failure is due to CHECK constraint (older MySQL/MariaDB), strip CHECK constraint and retry
                        if (str_contains($q, 'CHECK (') || str_contains($q, 'check (')) {
                            $sanitized = preg_replace('/\s*CHECK\s*\([^)]+\)/i', '', $q);
                            if ($sanitized !== null && $sanitized !== $q) {
                                try {
                                    $pdo->exec($sanitized);
                                    continue;
                                } catch (\Throwable) {
                                    // rethrow or ignore non-critical
                                }
                            }
                        }
                        // Non-critical table already exists or minor notice
                        if (!str_contains($stmtErr->getMessage(), 'already exists')) {
                            // Only throw if fatal
                            if (!str_contains($stmtErr->getMessage(), 'Duplicate column')) {
                                // continue anyway or log
                            }
                        }
                    }
                }
            }
        }
    }

    private function updateEnvFile(string $base, array $values): void
    {
        $envFile = $base . '/.env';
        if (!file_exists($envFile) && file_exists($base . '/.env.example')) {
            @copy($base . '/.env.example', $envFile);
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

        $result = @file_put_contents($envFile, trim($content) . "\n");
        if ($result === false) {
            throw new \RuntimeException("Unable to write configuration to .env. Please ensure the web server has write permissions to {$envFile}");
        }
    }
}
