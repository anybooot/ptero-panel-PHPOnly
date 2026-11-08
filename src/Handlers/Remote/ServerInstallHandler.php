<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Remote;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;

/**
 * Handles Egg installation script delivery and installation completion status from Wings.
 */
class ServerInstallHandler
{
    public function getInstallData(Request $request, string $uuid): Response
    {
        $node = $request->getAttribute('node');
        $server = Database::queryOne(
            "SELECT * FROM `servers` WHERE `uuid` = :uuid LIMIT 1",
            ['uuid' => $uuid]
        );

        if (!$server || ((int)$server['node_id']) !== ((int)$node['id'])) {
            return Response::json(['error' => 'Server not found on this node.'], 404);
        }

        $egg = Database::queryOne(
            "SELECT * FROM `eggs` WHERE `id` = :id LIMIT 1",
            ['id' => $server['egg_id']]
        );

        return Response::json([
            'container_image' => $egg['copy_script_container'] ?? 'ghcr.io/pterodactyl/installers:alpine',
            'entrypoint' => $egg['copy_script_entry'] ?? 'ash',
            'script' => $egg['copy_script_install'] ?? '',
        ]);
    }

    public function handleInstallComplete(Request $request, string $uuid): Response
    {
        $node = $request->getAttribute('node');
        $server = Database::queryOne(
            "SELECT * FROM `servers` WHERE `uuid` = :uuid LIMIT 1",
            ['uuid' => $uuid]
        );

        if (!$server || ((int)$server['node_id']) !== ((int)$node['id'])) {
            return Response::json(['error' => 'Server not found on this node.'], 404);
        }

        $successful = $request->boolean('successful');
        $reinstall = $request->boolean('reinstall');

        $status = null;
        if (!$successful) {
            $status = $reinstall ? 'reinstall_failed' : 'install_failed';
        }

        if ($server['status'] === 'suspended') {
            $status = 'suspended';
        }

        Database::execute(
            "UPDATE `servers` SET `status` = :status, `installed_at` = NOW() WHERE `id` = :id",
            ['status' => $status, 'id' => $server['id']]
        );

        return Response::noContent();
    }
}
