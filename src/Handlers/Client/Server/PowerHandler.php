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
            // Check what software is running and use its stop command if stopping or restarting
            if ($action === 'stop' || $action === 'restart') {
                $stopCommand = $this->getSoftwareStopCommand($server);
                $commandSent = false;

                if (!empty($stopCommand) && !str_starts_with($stopCommand, '^')) {
                    try {
                        WingsClient::sendCommands($server['node'], $server['uuid'], [$stopCommand]);
                        $commandSent = true;
                    } catch (\Throwable) {
                        // Fallback will execute below
                        $commandSent = false;
                    }
                }

                // If command could not be sent (or as fallback / for restart action), send direct power action to Wings
                if (!$commandSent || $action === 'restart') {
                    try {
                        WingsClient::sendPowerAction($server['node'], $server['uuid'], $action);
                    } catch (\Throwable $e) {
                        // If sending restart/stop to wings fails but console stop succeeded, allow graceful shutdown
                        if (!$commandSent) {
                            throw $e;
                        }
                    }
                }
            } else {
                // start and kill send direct power actions to Wings
                WingsClient::sendPowerAction($server['node'], $server['uuid'], $action);
            }

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
            // Final fallback: try direct Wings power action if not already tried
            try {
                WingsClient::sendPowerAction($server['node'], $server['uuid'], $action);
                return Response::noContent();
            } catch (\Throwable) {
                return Response::json(['error' => 'Unable to reach the node.'], 200);
            }
        }
    }

    /**
     * Inspects the server's egg and running service software to determine the appropriate stop command.
     */
    private function getSoftwareStopCommand(array $server): ?string
    {
        $egg = null;
        if (!empty($server['egg_id'])) {
            $egg = Database::queryOne("SELECT * FROM `eggs` WHERE `id` = :id LIMIT 1", ['id' => $server['egg_id']]);
        }

        // 1. Egg's explicitly configured stop command
        if (!empty($egg['config_stop'])) {
            $cmd = trim((string)$egg['config_stop']);
            if ($cmd !== '') {
                return $cmd;
            }
        }

        // 2. Intelligent detection based on egg name, server name, and egg features
        $ident = strtolower(($egg['name'] ?? '') . ' ' . ($server['name'] ?? ''));

        if (str_contains($ident, 'velocity') || str_contains($ident, 'bungee') || str_contains($ident, 'waterfall')) {
            return 'end';
        }

        if (
            str_contains($ident, 'minecraft') ||
            str_contains($ident, 'paper') ||
            str_contains($ident, 'spigot') ||
            str_contains($ident, 'purpur') ||
            str_contains($ident, 'vanilla') ||
            str_contains($ident, 'forge') ||
            str_contains($ident, 'fabric') ||
            str_contains($ident, 'bedrock')
        ) {
            return 'stop';
        }

        if (
            str_contains($ident, 'rust') ||
            str_contains($ident, 'source') ||
            str_contains($ident, 'csgo') ||
            str_contains($ident, 'counter-strike') ||
            str_contains($ident, 'tf2') ||
            str_contains($ident, 'garry') ||
            str_contains($ident, 'gmod') ||
            str_contains($ident, 'valheim')
        ) {
            return 'quit';
        }

        if (str_contains($ident, 'terraria') || str_contains($ident, 'tshock')) {
            return 'exit';
        }

        if (str_contains($ident, 'ark')) {
            return 'doexit';
        }

        return 'stop';
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
