<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Remote;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Wings\EggConfigurator;
use Pterodactyl\Wings\ServerConfigurator;

/**
 * Returns configuration for a specific server requested by Wings daemon.
 */
class ServerDetailsHandler
{
    public function __invoke(Request $request, string $uuid): Response
    {
        $node = $request->getAttribute('node');
        if (!$node) {
            return Response::json(['error' => 'Node not identified.'], 403);
        }

        $server = Database::queryOne(
            "SELECT * FROM `servers` WHERE `uuid` = :uuid LIMIT 1",
            ['uuid' => $uuid]
        );

        if (!$server) {
            return Response::json(['error' => 'Server not found.'], 404);
        }

        if (((int)$server['node_id']) !== ((int)$node['id'])) {
            return Response::json(['error' => 'Requesting node does not have permission to access this server.'], 403);
        }

        return Response::json([
            'settings' => ServerConfigurator::build($server),
            'process_configuration' => EggConfigurator::build($server),
        ]);
    }
}
