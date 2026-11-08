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
        $eggDir = __DIR__ . '/database/Seeders/eggs';
        if (!is_dir($eggDir)) {
            echo "Egg seeders directory not found.\n";
            exit(1);
        }

        try {
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
                        echo "  - Egg [{$eggName}] already exists, skipping.\n";
                        continue;
                    }

                    $eggId = Database::insert('eggs', [
                        'uuid' => bin2hex(random_bytes(16)),
                        'nest_id' => $nestId,
                        'author' => $data['author'] ?? 'support@pterodactyl.io',
                        'name' => $eggName,
                        'description' => $data['description'] ?? '',
                        'startup' => $data['startup'] ?? '',
                        'image' => is_array($data['docker_images'] ?? null) ? array_values($data['docker_images'])[0] : ($data['image'] ?? 'ghcr.io/pterodactyl/yolks:java_17'),
                        'docker_images' => json_encode($data['docker_images'] ?? []),
                        'inherit_config_startup' => json_encode($data['config']['startup'] ?? []),
                        'inherit_config_stop' => $data['config']['stop'] ?? 'stop',
                        'inherit_config_files' => json_encode($data['config']['files'] ?? new \stdClass()),
                        'copy_script_container' => $data['scripts']['installation']['container'] ?? 'ghcr.io/pterodactyl/installers:alpine',
                        'copy_script_entry' => $data['scripts']['installation']['entrypoint'] ?? 'ash',
                        'copy_script_install' => $data['scripts']['installation']['script'] ?? '',
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
                                'default_value' => $var['default_value'] ?? '',
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
        echo "  key:generate    Generate a random 32-byte APP_KEY and update .env\n";
        echo "  user:make       Create a new user or Root Administrator account\n";
        echo "  db:seed-eggs    Import official game Nests & Eggs from database/Seeders/eggs/\n";
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
