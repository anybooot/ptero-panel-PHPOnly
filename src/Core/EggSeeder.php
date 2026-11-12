<?php

declare(strict_types=1);

namespace Pterodactyl\Core;

class EggSeeder
{
    public static function seedDefaults(?callable $logger = null): void
    {
        $log = $logger ?? function (string $msg) {};

        $baseDir = dirname(__DIR__, 2);
        $minecraftDir = $baseDir . '/database/Seeders/eggs/minecraft';
        if (!is_dir($minecraftDir)) {
            return;
        }

        // 1. Ensure Minecraft Nest exists
        $nest = Database::queryOne("SELECT * FROM `nests` WHERE `name` = 'Minecraft' LIMIT 1");
        if (!$nest) {
            $nestId = Database::insert('nests', [
                'uuid' => bin2hex(random_bytes(16)),
                'author' => 'support@pterodactyl.io',
                'name' => 'Minecraft',
                'description' => 'Minecraft - the sandbox game developed by Mojang Studios and server software.',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $log("+ Created Nest: Minecraft\n");
        } else {
            $nestId = (int)$nest['id'];
        }

        // 2. Default target eggs: Paper and Velocity
        $targetEggs = [
            'Paper' => $minecraftDir . '/egg-paper.json',
            'Velocity' => $minecraftDir . '/egg-velocity.json',
        ];

        foreach ($targetEggs as $eggName => $filePath) {
            if (!file_exists($filePath)) {
                continue;
            }

            $existing = Database::queryOne(
                "SELECT `id` FROM `eggs` WHERE `nest_id` = :nest_id AND `name` = :name LIMIT 1",
                ['nest_id' => $nestId, 'name' => $eggName]
            );

            if ($existing) {
                $log("  - Egg [{$eggName}] already exists in database.\n");
                continue;
            }

            $data = json_decode((string)file_get_contents($filePath), true);
            if (!$data) {
                continue;
            }

            $dockerImages = $data['docker_images'] ?? ['Java 17' => 'ghcr.io/pterodactyl/yolks:java_17'];

            $uuid = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );

            $eggId = Database::insert('eggs', [
                'uuid' => $uuid,
                'nest_id' => $nestId,
                'author' => $data['author'] ?? 'support@pterodactyl.io',
                'name' => $eggName,
                'description' => $data['description'] ?? '',
                'features' => json_encode($data['features'] ?? []),
                'startup' => $data['startup'] ?? '',
                'docker_images' => json_encode($dockerImages),
                'config_startup' => is_string($data['config']['startup'] ?? null) ? $data['config']['startup'] : json_encode($data['config']['startup'] ?? []),
                'config_stop' => $data['config']['stop'] ?? 'stop',
                'config_files' => is_string($data['config']['files'] ?? null) ? $data['config']['files'] : json_encode($data['config']['files'] ?? new \stdClass()),
                'script_container' => $data['scripts']['installation']['container'] ?? 'ghcr.io/pterodactyl/installers:alpine',
                'script_entry' => $data['scripts']['installation']['entrypoint'] ?? 'ash',
                'script_install' => $data['scripts']['installation']['script'] ?? '',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

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

            $log("  ✓ Seeded Default Egg: {$eggName} (ID: {$eggId})\n");
        }
    }
}
