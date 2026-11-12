<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\ClientApi;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Security\Gate;
use Pterodactyl\Wings\WingsClient;

/**
 * Handles Client API endpoints conforming to Pterodactyl specifications.
 */
class ClientApiController
{
    public static function transformServer(array $s, array $user): array
    {
        $isOwner = ((int)$s['owner_id']) === ((int)$user['id']);

        $allocations = [];
        if (!empty($s['allocation_id'])) {
            $allocations[] = [
                'object' => 'server_allocation',
                'attributes' => [
                    'id' => (int)$s['allocation_id'],
                    'ip' => $s['allocation_ip'] ?? '127.0.0.1',
                    'ip_alias' => $s['allocation_alias'] ?: null,
                    'port' => (int)($s['allocation_port'] ?? 25565),
                    'notes' => null,
                    'is_default' => true,
                ],
            ];
        }

        return [
            'object' => 'server',
            'attributes' => [
                'server_owner' => $isOwner,
                'identifier' => $s['uuidShort'],
                'internal_id' => (int)$s['id'],
                'uuid' => $s['uuid'],
                'name' => $s['name'],
                'node' => $s['node_name'] ?? 'Local Node',
                'is_node_under_maintenance' => !empty($s['node_maintenance']),
                'status' => $s['status'] ?: null,
                'sftp_details' => [
                    'ip' => $s['node_fqdn'] ?? '127.0.0.1',
                    'port' => (int)($s['node_sftp'] ?? 2022),
                ],
                'description' => (string)($s['description'] ?? ''),
                'limits' => [
                    'memory' => (int)($s['memory'] ?? 1024),
                    'swap' => (int)($s['swap'] ?? 0),
                    'disk' => (int)($s['disk'] ?? 10240),
                    'io' => (int)($s['io'] ?? 500),
                    'cpu' => (int)($s['cpu'] ?? 100),
                    'threads' => $s['threads'] ?? null,
                    'oom_disabled' => !empty($s['oom_disabled']),
                ],
                'invocation' => $s['startup'] ?? '',
                'docker_image' => $s['image'] ?? 'ghcr.io/pterodactyl/yolks:java_17',
                'egg_features' => json_decode((string)($s['egg_features'] ?? '[]'), true) ?: [],
                'feature_limits' => [
                    'databases' => (int)($s['database_limit'] ?? 0),
                    'allocations' => (int)($s['allocation_limit'] ?? 1),
                    'backups' => (int)($s['backup_limit'] ?? 0),
                ],
                'is_suspended' => ($s['status'] === 'suspended'),
                'is_installing' => ($s['status'] === 'installing'),
                'is_transferring' => false,
                'relationships' => [
                    'allocations' => [
                        'object' => 'list',
                        'data' => $allocations,
                    ],
                ],
            ],
        ];
    }

    public function listServers(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $search = trim((string)$request->input('filter[name]', $request->input('search', '')));

        $query = "SELECT s.*, n.`name` as node_name, n.`fqdn` as node_fqdn, n.`daemonSFTP` as node_sftp, n.`maintenance_mode` as node_maintenance,
                         a.`ip` as allocation_ip, a.`port` as allocation_port, a.`ip_alias` as allocation_alias,
                         e.`features` as egg_features
                  FROM `servers` s
                  JOIN `nodes` n ON n.`id` = s.`node_id`
                  LEFT JOIN `allocations` a ON a.`id` = s.`allocation_id`
                  LEFT JOIN `eggs` e ON e.`id` = s.`egg_id`";

        $params = [];
        $where = [];

        if (!Gate::isRootAdmin($user)) {
            $where[] = "(s.`owner_id` = :user_id OR s.`id` IN (SELECT `server_id` FROM `subusers` WHERE `user_id` = :subuser_id))";
            $params['user_id'] = $user['id'];
            $params['subuser_id'] = $user['id'];
        }

        if ($search !== '') {
            $where[] = "(s.`name` LIKE :search OR s.`uuid` LIKE :search2 OR s.`uuidShort` LIKE :search3)";
            $params['search'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
            $params['search3'] = "%{$search}%";
        }

        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }

        $query .= " ORDER BY s.`name` ASC";
        $servers = Database::query($query, $params);

        $data = array_map(fn($s) => self::transformServer($s, $user), $servers);

        return Response::json([
            'object' => 'list',
            'data' => $data,
            'meta' => [
                'pagination' => [
                    'total' => count($data),
                    'count' => count($data),
                    'per_page' => 50,
                    'current_page' => 1,
                    'total_pages' => 1,
                ],
            ],
        ]);
    }

    public function viewServer(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        $node = $server['node'] ?? Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $server['node_id']]);
        $alloc = Database::queryOne("SELECT * FROM `allocations` WHERE `id` = :id LIMIT 1", ['id' => $server['allocation_id']]);
        $egg = Database::queryOne("SELECT * FROM `eggs` WHERE `id` = :id LIMIT 1", ['id' => $server['egg_id']]);

        $serverData = array_merge($server, [
            'node_name' => $node['name'] ?? 'Local Node',
            'node_fqdn' => $node['fqdn'] ?? '127.0.0.1',
            'node_sftp' => $node['daemonSFTP'] ?? 2022,
            'node_maintenance' => $node['maintenance_mode'] ?? 0,
            'allocation_ip' => $alloc['ip'] ?? '127.0.0.1',
            'allocation_port' => $alloc['port'] ?? 25565,
            'allocation_alias' => $alloc['ip_alias'] ?? null,
            'egg_features' => $egg['features'] ?? '[]',
        ]);

        return Response::json(self::transformServer($serverData, $user));
    }

    public function getAccount(Request $request): Response
    {
        $user = $request->getAttribute('user');
        return Response::json([
            'object' => 'user',
            'attributes' => [
                'id' => (int)$user['id'],
                'admin' => !empty($user['root_admin']),
                'username' => $user['username'],
                'email' => $user['email'],
                'first_name' => $user['name_first'] ?? $user['username'],
                'last_name' => $user['name_last'] ?? '',
                'language' => $user['language'] ?? 'en',
            ],
        ]);
    }

    public function getResources(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::hasServerAccess($user, $server)) {
            return Response::json(['errors' => [['code' => 'AccessDeniedHttpException', 'status' => '403', 'detail' => 'You do not have permission to view this server.']]], 403);
        }

        $state = 'offline';
        $stats = [
            'memory_bytes' => 0,
            'cpu_absolute' => 0,
            'disk_bytes' => 0,
            'network_rx_bytes' => 0,
            'network_tx_bytes' => 0,
            'uptime' => 0,
        ];

        try {
            $remote = WingsClient::getSystemInfo($server['node']);
            if (!empty($remote)) {
                $state = 'running';
            }
        } catch (\Throwable) {
            // Keep default offline
        }

        return Response::json([
            'object' => 'stats',
            'attributes' => [
                'current_state' => $state,
                'is_suspended' => ($server['status'] === 'suspended'),
                'resources' => $stats,
            ],
        ]);
    }

    public function getServerActivity(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'activity.read')) {
            return Response::json(['errors' => [['code' => 'AccessDeniedHttpException', 'status' => '403', 'detail' => 'You do not have permission to view server activity.']]], 403);
        }

        $logs = Database::query(
            "SELECT al.`id`, al.`batch`, al.`event`, al.`ip`, al.`description`, al.`properties`, al.`timestamp`,
                    u.`username` as actor_username, u.`email` as actor_email
             FROM `activity_log_subjects` als
             JOIN `activity_logs` al ON al.`id` = als.`activity_log_id`
             LEFT JOIN `users` u ON u.`id` = al.`actor_id` AND (al.`actor_type` IN ('user', 'App\\\\Models\\\\User') OR al.`actor_type` IS NULL)
             WHERE als.`subject_id` = :server_id AND als.`subject_type` IN ('server', 'App\\\\Models\\\\Server')
             ORDER BY al.`timestamp` DESC LIMIT 50",
            ['server_id' => $server['id']]
        );

        $data = array_map(function ($l) {
            return [
                'object' => 'activity_log',
                'attributes' => [
                    'id' => (string)$l['id'],
                    'batch' => $l['batch'],
                    'event' => $l['event'],
                    'is_api' => false,
                    'ip' => $l['ip'],
                    'description' => $l['description'],
                    'properties' => json_decode((string)$l['properties'], true) ?: new \stdClass(),
                    'has_additional_metadata' => false,
                    'timestamp' => date('c', strtotime($l['timestamp'])),
                ],
                'relationships' => [
                    'actor' => !empty($l['actor_username']) ? [
                        'object' => 'user',
                        'attributes' => [
                            'username' => $l['actor_username'],
                            'email' => $l['actor_email'],
                        ],
                    ] : null,
                ],
            ];
        }, $logs);

        return Response::json([
            'object' => 'list',
            'data' => $data,
            'meta' => [
                'pagination' => [
                    'total' => count($data),
                    'count' => count($data),
                    'per_page' => 50,
                    'current_page' => 1,
                    'total_pages' => 1,
                ],
            ],
        ]);
    }

    public function getPermissions(Request $request): Response
    {
        $permissions = [
            'websocket' => [
                'description' => 'Allows connecting to the server websocket instance to read console output and stats.',
                'keys' => [
                    'connect' => 'Allows a user to connect to the websocket instance for a server to stream the console.',
                ],
            ],
            'control' => [
                'description' => 'Permissions that control a user\'s ability to control the power state of a server, or send commands.',
                'keys' => [
                    'console' => 'Allows a user to send commands to the server instance via the console.',
                    'start' => 'Allows a user to start the server instance.',
                    'stop' => 'Allows a user to stop the server instance.',
                    'restart' => 'Allows a user to restart the server instance.',
                ],
            ],
            'user' => [
                'description' => 'Permissions that allow a user to manage other subusers on a server.',
                'keys' => [
                    'create' => 'Allows a user to create new subusers for the server.',
                    'read' => 'Allows a user to view subusers and their permissions for the server.',
                    'update' => 'Allows a user to modify other subusers on the server.',
                    'delete' => 'Allows a user to delete a subuser from the server.',
                ],
            ],
            'file' => [
                'description' => 'Permissions that control a user\'s ability to modify the filesystem for this server instance.',
                'keys' => [
                    'create' => 'Allows a user to create additional files and folders via the web panel.',
                    'read' => 'Allows a user to inspect the contents of the directory and view files.',
                    'read-content' => 'Allows a user to view the contents of a given file.',
                    'save' => 'Allows a user to save modified file contents.',
                    'update' => 'Allows a user to rename and move files or directories.',
                    'delete' => 'Allows a user to delete files or directories.',
                    'archive' => 'Allows a user to archive and compress directory contents.',
                    'sftp' => 'Allows a user to connect to SFTP and manage server files.',
                ],
            ],
            'backup' => [
                'description' => 'Permissions that control a user\'s ability to generate and manage server backups.',
                'keys' => [
                    'create' => 'Allows a user to create new backups for this server.',
                    'read' => 'Allows a user to view all backups that exist for this server.',
                    'delete' => 'Allows a user to remove backups from the system.',
                    'download' => 'Allows a user to download a backup for a server.',
                    'restore' => 'Allows a user to restore a backup for the server.',
                ],
            ],
            'allocation' => [
                'description' => 'Permissions that control a user\'s ability to modify the port allocations for this server.',
                'keys' => [
                    'read' => 'Allows a user to view all allocations currently assigned to this server.',
                    'create' => 'Allows a user to assign additional allocations to the server.',
                    'update' => 'Allows a user to change the primary server allocation and attach notes to each allocation.',
                    'delete' => 'Allows a user to delete an allocation from the server.',
                ],
            ],
            'startup' => [
                'description' => 'Permissions that control a user\'s ability to view and modify startup parameters.',
                'keys' => [
                    'read' => 'Allows a user to view the startup parameters for a server.',
                    'update' => 'Allows a user to modify the startup parameters for the server.',
                    'docker-image' => 'Allows a user to modify the Docker image used when running the server.',
                ],
            ],
            'database' => [
                'description' => 'Permissions that control a user\'s ability to view and manage server databases.',
                'keys' => [
                    'create' => 'Allows a user to create a new database for this server.',
                    'read' => 'Allows a user to view the database credentials for this server.',
                    'update' => 'Allows a user to rotate the password for a database.',
                    'delete' => 'Allows a user to remove a database from this server.',
                ],
            ],
            'schedule' => [
                'description' => 'Permissions that control a user\'s ability to view and manage server schedules.',
                'keys' => [
                    'create' => 'Allows a user to create new schedules for this server.',
                    'read' => 'Allows a user to view all schedules for this server.',
                    'update' => 'Allows a user to update schedules for this server.',
                    'delete' => 'Allows a user to delete schedules for this server.',
                ],
            ],
            'settings' => [
                'description' => 'Permissions that control a user\'s ability to modify server settings.',
                'keys' => [
                    'rename' => 'Allows a user to rename this server.',
                    'reinstall' => 'Allows a user to trigger a reinstall of this server.',
                ],
            ],
            'activity' => [
                'description' => 'Permissions that control a user\'s ability to view server activity logs.',
                'keys' => [
                    'read' => 'Allows a user to view the activity and audit logs for this server.',
                ],
            ],
        ];

        return Response::json([
            'object' => 'system_permissions',
            'attributes' => [
                'permissions' => $permissions,
            ],
        ]);
    }
}
