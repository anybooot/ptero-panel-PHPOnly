<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Client\Server;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Security\Gate;
use Pterodactyl\Wings\WingsClient;

/**
 * Handles server power actions (start, stop, restart, kill).
 */
class PowerHandler
{
    public function sendPower(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');
        $action = (string)$request->input('action', '');

        if (!in_array($action, ['start', 'stop', 'restart', 'kill'], true)) {
            return Response::json(['error' => 'Invalid power action specified.'], 400);
        }

        $requiredPermission = "control.{$action}";
        if ($action === 'kill') {
            $requiredPermission = 'control.stop';
        }

        if (!Gate::allows($user, $server, $requiredPermission)) {
            return Response::json(['error' => 'You do not have permission to execute this power action.'], 403);
        }

        try {
            WingsClient::sendPowerAction($server['node'], $server['uuid'], $action);

            // Update database status immediately
            $newStatus = match ($action) {
                'start', 'restart' => 'starting',
                'stop' => 'stopping',
                'kill' => 'offline',
                default => null,
            };
            if ($newStatus) {
                Database::execute(
                    "UPDATE `servers` SET `status` = :st WHERE `id` = :id AND (`status` IS NULL OR `status` NOT IN ('suspended', 'installing', 'restoring_backup'))",
                    ['st' => $newStatus, 'id' => $server['id']]
                );
            }

            \Pterodactyl\Core\ActivityLogger::log(
                "server:power.{$action}",
                $user,
                ['action' => $action],
                $request,
                $server
            );

            return Response::noContent();
        } catch (\Throwable $e) {
            return Response::json(['error' => 'Unable to reach the node.'], 200);
        }
    }

    public function updateStatus(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $status = trim((string)$request->input('status', ''));
        if (!in_array($status, ['running', 'starting', 'stopping', 'offline'], true)) {
            return Response::json(['error' => 'Invalid status specified.'], 400);
        }

        Database::execute(
            "UPDATE `servers` SET `status` = :st WHERE `id` = :id AND (`status` IS NULL OR `status` NOT IN ('suspended', 'installing', 'restoring_backup'))",
            ['st' => $status, 'id' => $server['id']]
        );

        return Response::json(['status' => $status]);
    }

    public function getStatus(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $row = Database::queryOne("SELECT `status` FROM `servers` WHERE `id` = :id", ['id' => $server['id']]);
        return Response::json(['status' => $row['status'] ?: 'offline']);
    }
}
