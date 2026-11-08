<?php

declare(strict_types=1);

namespace Pterodactyl\Wings;

use Pterodactyl\Core\Database;
use Pterodactyl\Security\Encrypter;

/**
 * Builds the exact configuration payload expected by Pterodactyl Wings Daemon for servers.
 */
class ServerConfigurator
{
    public static function build(array $server): array
    {
        $egg = Database::queryOne("SELECT * FROM `eggs` WHERE `id` = :id LIMIT 1", ['id' => $server['egg_id']]);
        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $server['node_id']]);
        $location = Database::queryOne("SELECT * FROM `locations` WHERE `id` = :id LIMIT 1", ['id' => $node['location_id'] ?? 0]);

        $defaultAllocation = Database::queryOne(
            "SELECT * FROM `allocations` WHERE `id` = :id LIMIT 1",
            ['id' => $server['allocation_id']]
        ) ?: ['ip' => '0.0.0.0', 'port' => 25565];

        $allocations = Database::query(
            "SELECT * FROM `allocations` WHERE `server_id` = :server_id",
            ['server_id' => $server['id']]
        );

        $mappings = [];
        foreach ($allocations as $alloc) {
            $ip = $alloc['ip'];
            if (!isset($mappings[$ip])) {
                $mappings[$ip] = [];
            }
            $mappings[$ip][] = (int)$alloc['port'];
        }

        // Environment variables
        $env = self::buildEnvironment($server, $egg, $location);

        // Mounts
        $mountRows = Database::query(
            "SELECT m.`source`, m.`target`, m.`read_only` FROM `mount_server` ms
             JOIN `mounts` m ON m.`id` = ms.`mount_id`
             WHERE ms.`server_id` = :server_id",
            ['server_id' => $server['id']]
        );
        $mounts = array_map(fn($m) => [
            'source' => $m['source'],
            'target' => $m['target'],
            'read_only' => (bool)$m['read_only'],
        ], $mountRows);

        // File denylist
        $fileDenylist = [];
        if (!empty($egg['inherit_file_denylist'])) {
            $decoded = json_decode($egg['inherit_file_denylist'], true);
            if (is_array($decoded)) {
                $fileDenylist = $decoded;
            }
        }

        return [
            'uuid' => $server['uuid'],
            'meta' => [
                'name' => $server['name'],
                'description' => $server['description'] ?? '',
            ],
            'suspended' => (bool)($server['status'] === 'suspended'),
            'environment' => $env,
            'invocation' => $server['startup'],
            'skip_egg_scripts' => (bool)($server['skip_scripts'] ?? false),
            'build' => [
                'memory_limit' => (int)$server['memory'],
                'swap' => (int)$server['swap'],
                'io_weight' => (int)$server['io'],
                'cpu_limit' => (int)$server['cpu'],
                'threads' => $server['threads'] ?? null,
                'disk_space' => (int)$server['disk'],
                'oom_disabled' => (bool)($server['oom_disabled'] ?? true),
            ],
            'container' => [
                'image' => $server['image'],
                'oom_disabled' => (bool)($server['oom_disabled'] ?? true),
                'requires_rebuild' => false,
            ],
            'allocations' => [
                'force_outgoing_ip' => (bool)($egg['force_outgoing_ip'] ?? false),
                'default' => [
                    'ip' => $defaultAllocation['ip'],
                    'port' => (int)$defaultAllocation['port'],
                ],
                'mappings' => $mappings,
            ],
            'mounts' => $mounts,
            'egg' => [
                'id' => $egg['uuid'] ?? '',
                'file_denylist' => $fileDenylist,
            ],
        ];
    }

    public static function buildLegacy(array $server): array
    {
        $defaultAllocation = Database::queryOne(
            "SELECT * FROM `allocations` WHERE `id` = :id LIMIT 1",
            ['id' => $server['allocation_id']]
        ) ?: ['ip' => '0.0.0.0', 'port' => 25565];

        $allocations = Database::query(
            "SELECT * FROM `allocations` WHERE `server_id` = :server_id",
            ['server_id' => $server['id']]
        );
        $ports = [];
        foreach ($allocations as $alloc) {
            $ip = $alloc['ip'];
            if (!isset($ports[$ip])) {
                $ports[$ip] = [];
            }
            $ports[$ip][] = (int)$alloc['port'];
        }

        $egg = Database::queryOne("SELECT * FROM `eggs` WHERE `id` = :id LIMIT 1", ['id' => $server['egg_id']]);
        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $server['node_id']]);
        $location = Database::queryOne("SELECT * FROM `locations` WHERE `id` = :id LIMIT 1", ['id' => $node['location_id'] ?? 0]);

        return [
            'uuid' => $server['uuid'],
            'build' => [
                'default' => [
                    'ip' => $defaultAllocation['ip'],
                    'port' => (int)$defaultAllocation['port'],
                ],
                'ports' => $ports,
                'env' => self::buildEnvironment($server, $egg, $location),
                'oom_disabled' => (bool)($server['oom_disabled'] ?? true),
                'memory' => (int)$server['memory'],
                'swap' => (int)$server['swap'],
                'io' => (int)$server['io'],
                'cpu' => (int)$server['cpu'],
                'threads' => $server['threads'] ?? null,
                'disk' => (int)$server['disk'],
                'image' => $server['image'],
            ],
            'service' => [
                'egg' => $egg['uuid'] ?? '',
                'skip_scripts' => (bool)($server['skip_scripts'] ?? false),
            ],
            'rebuild' => false,
            'suspended' => ($server['status'] === 'suspended') ? 1 : 0,
        ];
    }

    private static function buildEnvironment(array $server, ?array $egg, ?array $location): array
    {
        $env = [];

        // Fetch egg variables
        if ($egg) {
            $variables = Database::query(
                "SELECT ev.`env_variable`, ev.`default_value`, ev.`user_viewable`, sv.`server_value`
                 FROM `egg_variables` ev
                 LEFT JOIN `server_variables` sv ON sv.`variable_id` = ev.`id` AND sv.`server_id` = :server_id
                 WHERE ev.`egg_id` = :egg_id",
                ['server_id' => $server['id'], 'egg_id' => $egg['id']]
            );

            foreach ($variables as $var) {
                $val = $var['server_value'] ?? $var['default_value'];
                $env[$var['env_variable']] = (string)$val;
            }
        }

        // Standard variables
        $env['STARTUP'] = $server['startup'];
        $env['P_SERVER_LOCATION'] = $location['short'] ?? 'local';
        $env['P_SERVER_UUID'] = $server['uuid'];

        return $env;
    }
}
