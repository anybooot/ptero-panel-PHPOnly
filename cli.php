<?php

declare(strict_types=1);

/**
 * Pterodactyl Panel - CLI Helper Tool
 * Allows managing the panel directly from the terminal without external frameworks.
 */

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

require_once __DIR__ . '/src/autoload.php';

use Pterodactyl\Core\Config;
use Pterodactyl\Core\Database;

Config::load(__DIR__);

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'p:environment:setup':
    case 'env:setup':
        echo "=== Configure Environment (.env) ===\n";
        $envFile = __DIR__ . '/.env';
        if (!file_exists($envFile) && file_exists(__DIR__ . '/.env.example')) {
            copy(__DIR__ . '/.env.example', $envFile);
        }

        $appUrl = prompt("Panel URL [http://localhost:8000]: ") ?: 'http://localhost:8000';
        $appTz = prompt("Timezone [UTC]: ") ?: 'UTC';
        $dbHost = prompt("Database Host [127.0.0.1]: ") ?: '127.0.0.1';
        $dbPort = prompt("Database Port [3306]: ") ?: '3306';
        $dbName = prompt("Database Name [panel]: ") ?: 'panel';
        $dbUser = prompt("Database User [pterodactyl]: ") ?: 'pterodactyl';
        $dbPass = promptPassword("Database Password: ");

        $content = file_exists($envFile) ? file_get_contents($envFile) : '';
        $replacements = [
            'APP_URL' => $appUrl,
            'APP_TIMEZONE' => $appTz,
            'DB_HOST' => $dbHost,
            'DB_PORT' => $dbPort,
            'DB_DATABASE' => $dbName,
            'DB_USERNAME' => $dbUser,
            'DB_PASSWORD' => $dbPass,
        ];

        foreach ($replacements as $key => $val) {
            if (preg_match("/^{$key}=.*$/m", $content)) {
                $content = preg_replace("/^{$key}=.*$/m", "{$key}={$val}", $content);
            } else {
                $content .= "\n{$key}={$val}";
            }
        }

        file_put_contents($envFile, $content);
        echo "✓ Environment configuration saved to .env!\n";
        break;

    case 'migrate':
    case 'db:migrate':
        echo "=== Running Database Migrations ===\n";
        $schemaFile = __DIR__ . '/database/schema/mysql-schema.sql';
        if (!file_exists($schemaFile)) {
            echo "Error: Schema file database/schema/mysql-schema.sql not found.\n";
            exit(1);
        }

        $host = Config::get('DB_HOST', '127.0.0.1');
        $port = Config::get('DB_PORT', '3306');
        $database = Config::get('DB_DATABASE', 'panel');
        $username = Config::get('DB_USERNAME', 'pterodactyl');
        $password = Config::get('DB_PASSWORD', '');

        try {
            // First connect to server and ensure database exists
            $serverPdo = new PDO("mysql:host={$host};port={$port}", (string)$username, (string)$password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "+ Verified database `{$database}` exists.\n";

            // Now connect to the database and execute schema
            $dbPdo = new PDO("mysql:host={$host};port={$port};dbname={$database}", (string)$username, (string)$password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);

            $sql = file_get_contents($schemaFile);
            $dbPdo->exec($sql);

            $stmt = $dbPdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            echo "✓ Database schema applied successfully! (" . count($tables) . " tables verified in `{$database}`).\n";
            echo "+ Seeding default eggs (Minecraft Paper & Velocity)...\n";
            \Pterodactyl\Core\EggSeeder::seedDefaults(fn($m) => print($m));
        } catch (\Throwable $e) {
            echo "Migration error: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;

    case 'install':
        echo "=========================================================\n";
        echo "  Pterodactyl Panel - Pure PHP 8.2+ Automated Installer  \n";
        echo "=========================================================\n\n";

        // Step 1: Check .env and APP_KEY
        $envFile = __DIR__ . '/.env';
        if (!file_exists($envFile)) {
            if (file_exists(__DIR__ . '/.env.example')) {
                copy(__DIR__ . '/.env.example', $envFile);
                echo "1. Created .env from .env.example\n";
            }
        }

        $content = file_exists($envFile) ? file_get_contents($envFile) : '';
        if (!preg_match('/^APP_KEY=base64:.+$/m', $content)) {
            $key = 'base64:' . base64_encode(random_bytes(32));
            if (preg_match('/^APP_KEY=.*$/m', $content)) {
                $content = preg_replace('/^APP_KEY=.*$/m', "APP_KEY={$key}", $content);
            } else {
                $content .= "\nAPP_KEY={$key}\n";
            }
            file_put_contents($envFile, $content);
            echo "✓ Generated fresh APP_KEY in .env\n";
        }

        // Re-read config
        Config::load(__DIR__);

        // Step 2: Database Setup & Migration
        echo "\n2. Database Configuration\n";
        $runEnvSetup = prompt("Would you like to configure database credentials now? (yes/no) [no]: ");
        if (strtolower(trim($runEnvSetup)) === 'yes' || strtolower(trim($runEnvSetup)) === 'y') {
            $dbHost = prompt("Database Host [127.0.0.1]: ") ?: '127.0.0.1';
            $dbPort = prompt("Database Port [3306]: ") ?: '3306';
            $dbName = prompt("Database Name [panel]: ") ?: 'panel';
            $dbUser = prompt("Database User [pterodactyl]: ") ?: 'pterodactyl';
            $dbPass = promptPassword("Database Password: ");

            $content = file_get_contents($envFile);
            $replacements = [
                'DB_HOST' => $dbHost,
                'DB_PORT' => $dbPort,
                'DB_DATABASE' => $dbName,
                'DB_USERNAME' => $dbUser,
                'DB_PASSWORD' => $dbPass,
            ];
            foreach ($replacements as $k => $v) {
                if (preg_match("/^{$k}=.*$/m", $content)) {
                    $content = preg_replace("/^{$k}=.*$/m", "{$k}={$v}", $content);
                } else {
                    $content .= "\n{$k}={$v}";
                }
            }
            file_put_contents($envFile, $content);
            Config::load(__DIR__);
        }

        // Run migrations
        echo "\n3. Migrating Database Tables...\n";
        $schemaFile = __DIR__ . '/database/schema/mysql-schema.sql';
        try {
            $host = Config::get('DB_HOST', '127.0.0.1');
            $port = Config::get('DB_PORT', '3306');
            $database = Config::get('DB_DATABASE', 'panel');
            $username = Config::get('DB_USERNAME', 'pterodactyl');
            $password = Config::get('DB_PASSWORD', '');

            $serverPdo = new PDO("mysql:host={$host};port={$port}", (string)$username, (string)$password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $dbPdo = new PDO("mysql:host={$host};port={$port};dbname={$database}", (string)$username, (string)$password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
            $dbPdo->exec(file_get_contents($schemaFile));
            $tablesCount = count($dbPdo->query("SHOW TABLES")->fetchAll());
            echo "✓ Database schema applied ({$tablesCount} tables ready)!\n";
        } catch (\Throwable $e) {
            echo "Database error during migration: " . $e->getMessage() . "\n";
            echo "Please verify database credentials in .env and run 'php cli.php db:migrate'.\n";
        }

        // Step 4: Seed Eggs
        echo "\n4. Seeding Default Game Nests & Eggs...\n";
        try {
            passthru('php cli.php db:seed-eggs');
        } catch (\Throwable) {}

        // Step 5: Create Administrator User
        echo "\n5. Create Root Administrator Account\n";
        $makeUser = prompt("Would you like to create an Administrator account now? (yes/no) [yes]: ");
        if (strtolower(trim($makeUser)) !== 'no' && strtolower(trim($makeUser)) !== 'n') {
            passthru('php cli.php user:make');
        }

        echo "\n=========================================================\n";
        echo "  Setup Complete! Pterodactyl Panel is ready to run.     \n";
        echo "  You can start the built-in server with:               \n";
        echo "  php -S 0.0.0.0:8000 -t public                         \n";
        echo "=========================================================\n";
        break;

    case 'key:generate':
        $key = 'base64:' . base64_encode(random_bytes(32));
        $envFile = __DIR__ . '/.env';
        if (file_exists($envFile)) {
            $content = file_get_contents($envFile);
            if (preg_match('/^APP_KEY=.*$/m', $content)) {
                $content = preg_replace('/^APP_KEY=.*$/m', "APP_KEY={$key}", $content);
            } else {
                $content .= "\nAPP_KEY={$key}\n";
            }
            file_put_contents($envFile, $content);
            echo "Successfully generated and saved APP_KEY to .env: {$key}\n";
        } else {
            echo "Generated APP_KEY: {$key}\n";
            echo "Please copy .env.example to .env and set APP_KEY={$key}\n";
        }
        break;

    case 'user:make':
        echo "=== Create Administrator / User ===\n";
        
        $email = prompt("Email Address: ");
        $username = prompt("Username: ");
        $firstName = prompt("First Name: ");
        $lastName = prompt("Last Name: ");
        $password = promptPassword("Password: ");
        $isAdmin = prompt("Is this user a Root Administrator? (yes/no) [yes]: ");
        $rootAdmin = (strtolower(trim($isAdmin)) === 'no' || strtolower(trim($isAdmin)) === 'n') ? 0 : 1;

        if (empty($email) || empty($username) || empty($password)) {
            echo "Error: Email, Username, and Password cannot be empty.\n";
            exit(1);
        }

        try {
            $existing = Database::queryOne(
                "SELECT `id` FROM `users` WHERE `email` = :email OR `username` = :username LIMIT 1",
                ['email' => $email, 'username' => $username]
            );

            if ($existing) {
                echo "Error: A user with that email or username already exists.\n";
                exit(1);
            }

            $uuid = bin2hex(random_bytes(16));
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            $id = Database::insert('users', [
                'uuid' => $uuid,
                'email' => $email,
                'username' => $username,
                'name_first' => $firstName ?: $username,
                'name_last' => $lastName ?: '',
                'password' => $hashedPassword,
                'root_admin' => $rootAdmin,
                'language' => 'en',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            echo "✓ User [{$username}] created successfully with ID: {$id}!\n";
        } catch (\Throwable $e) {
            echo "Database error: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;

    case 'db:seed-eggs':
        echo "=== Seeding Default Game Nests & Eggs ===\n";
        try {
            echo "1. Seeding Priority Default Eggs (Minecraft Paper & Velocity)...\n";
            \Pterodactyl\Core\EggSeeder::seedDefaults(fn($m) => print($m));

            echo "\n2. Scanning additional egg seeders...\n";
            $eggDir = __DIR__ . '/database/Seeders/eggs';
            if (is_dir($eggDir)) {
                $categories = [
                    'minecraft' => 'Minecraft',
                    'source-engine' => 'Source Engine',
                    'voice-servers' => 'Voice Servers',
                    'rust' => 'Rust',
                ];

                foreach ($categories as $folder => $nestName) {
                    $dirPath = "{$eggDir}/{$folder}";
                    if (!is_dir($dirPath)) continue;

                    $nest = Database::queryOne("SELECT * FROM `nests` WHERE `name` = :name LIMIT 1", ['name' => $nestName]);
                    if (!$nest) {
                        $nestId = Database::insert('nests', [
                            'uuid' => bin2hex(random_bytes(16)),
                            'author' => 'support@pterodactyl.io',
                            'name' => $nestName,
                            'description' => "Official {$nestName} game servers.",
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                        echo "+ Created Nest: {$nestName}\n";
                    } else {
                        $nestId = $nest['id'];
                    }

                    $files = glob("{$dirPath}/*.json");
                    foreach ($files as $jsonFile) {
                        $data = json_decode(file_get_contents($jsonFile), true);
                        if (!$data || !isset($data['name'])) continue;

                        $eggName = $data['name'];
                        $existingEgg = Database::queryOne(
                            "SELECT `id` FROM `eggs` WHERE `nest_id` = :nest_id AND `name` = :name LIMIT 1",
                            ['nest_id' => $nestId, 'name' => $eggName]
                        );

                        if ($existingEgg) {
                            continue;
                        }

                        $eggUuid = sprintf(
                            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                            mt_rand(0, 0xffff),
                            mt_rand(0, 0x0fff) | 0x4000,
                            mt_rand(0, 0x3fff) | 0x8000,
                            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                        );

                        $eggId = Database::insert('eggs', [
                            'uuid' => $eggUuid,
                            'nest_id' => $nestId,
                            'author' => $data['author'] ?? 'support@pterodactyl.io',
                            'name' => $eggName,
                            'description' => $data['description'] ?? '',
                            'startup' => $data['startup'] ?? '',
                            'docker_images' => json_encode($data['docker_images'] ?? []),
                            'config_startup' => is_string($data['config']['startup'] ?? null) ? $data['config']['startup'] : json_encode($data['config']['startup'] ?? []),
                            'config_stop' => $data['config']['stop'] ?? 'stop',
                            'config_files' => is_string($data['config']['files'] ?? null) ? $data['config']['files'] : json_encode($data['config']['files'] ?? new \stdClass()),
                            'script_container' => $data['scripts']['installation']['container'] ?? 'ghcr.io/pterodactyl/installers:alpine',
                            'script_entry' => $data['scripts']['installation']['entrypoint'] ?? 'ash',
                            'script_install' => $data['scripts']['installation']['script'] ?? '',
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);

                        // Seed egg variables
                        if (!empty($data['variables']) && is_array($data['variables'])) {
                            foreach ($data['variables'] as $var) {
                                Database::insert('egg_variables', [
                                    'egg_id' => $eggId,
                                    'name' => $var['name'] ?? '',
                                    'description' => $var['description'] ?? '',
                                    'env_variable' => $var['env_variable'] ?? '',
                                    'default_value' => (string)($var['default_value'] ?? ''),
                                    'user_viewable' => !empty($var['user_viewable']) ? 1 : 0,
                                    'user_editable' => !empty($var['user_editable']) ? 1 : 0,
                                    'rules' => $var['rules'] ?? 'required|string',
                                    'created_at' => date('Y-m-d H:i:s'),
                                    'updated_at' => date('Y-m-d H:i:s'),
                                ]);
                            }
                        }

                        echo "  + Imported Egg: {$eggName}\n";
                    }
                }
            }
            echo "✓ Seeding complete!\n";
        } catch (\Throwable $e) {
            echo "Seeding error: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;

    case 'help':
    default:
        echo "Pterodactyl Panel - Native PHP CLI Helper\n\n";
        echo "Usage: php cli.php [command]\n\n";
        echo "Available commands:\n";
        echo "  install         Complete guided installer (env, key, migrate, seeds, admin)\n";
        echo "  env:setup       Interactively configure environment variables (.env)\n";
        echo "  key:generate    Generate a random 32-byte APP_KEY and update .env\n";
        echo "  db:migrate      Execute database migrations and verify schema\n";
        echo "  db:seed-eggs    Import official game Nests & Eggs from database/Seeders/eggs/\n";
        echo "  user:make       Create a new user or Root Administrator account\n";
        echo "  help            Show this help information\n";
        break;
}

function prompt(string $message): string {
    echo $message;
    return trim((string)fgets(STDIN));
}

function promptPassword(string $message): string {
    echo $message;
    return trim((string)fgets(STDIN));
}
