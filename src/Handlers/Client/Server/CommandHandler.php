<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Client\Server;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Security\Gate;
use Pterodactyl\Wings\WingsClient;

/**
 * Handles sending console commands to the server via Wings.
 */
class CommandHandler
{
    public function sendCommand(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');
        $command = trim((string)$request->input('command', ''));

        if ($command === '') {
            return Response::json(['error' => 'A command must be provided.'], 400);
        }

        if (!Gate::allows($user, $server, 'control.console')) {
            return Response::json(['error' => 'You do not have permission to send commands to this server.'], 403);
        }

        try {
            WingsClient::sendCommands($server['node'], $server['uuid'], $command);

            Database::insert('activity_logs', [
                'ip' => $request->getClientIp(),
                'event' => 'server:command',
                'properties' => json_encode(['command' => $command]),
                'timestamp' => date('Y-m-d H:i:s'),
                'actor_id' => $user['id'],
                'actor_type' => 'user',
            ]);

            return Response::noContent();
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }
}
