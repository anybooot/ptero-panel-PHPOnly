<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Remote;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Wings\EggConfigurator;
use Pterodactyl\Wings\ServerConfigurator;

/**
 * Returns a paginated list of servers and configurations assigned to the requesting Wings node.
 */
class ServerListHandler
{
    public function __invoke(Request $request): Response
    {
        $node = $request->getAttribute('node');
        if (!$node) {
            return Response::json(['error' => 'Node not identified.'], 403);
        }

        $page = max(1, (int)$request->input('page', 1));
        $perPage = min(100, max(1, (int)$request->input('per_page', 50)));
        $offset = ($page - 1) * $perPage;

        $totalRow = Database::queryOne(
            "SELECT COUNT(*) as count FROM `servers` WHERE `node_id` = :node_id",
            ['node_id' => $node['id']]
        );
        $total = (int)($totalRow['count'] ?? 0);
        $lastPage = max(1, (int)ceil($total / $perPage));

        $servers = Database::query(
            "SELECT * FROM `servers` WHERE `node_id` = :node_id ORDER BY `id` ASC LIMIT {$perPage} OFFSET {$offset}",
            ['node_id' => $node['id']]
        );

        $data = [];
        foreach ($servers as $server) {
            $data[] = [
                'uuid' => $server['uuid'],
                'settings' => ServerConfigurator::build($server),
                'process_configuration' => EggConfigurator::build($server),
            ];
        }

        return Response::json([
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'from' => $total > 0 ? $offset + 1 : null,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'to' => min($offset + $perPage, $total),
                'total' => $total,
            ],
        ]);
    }
}
