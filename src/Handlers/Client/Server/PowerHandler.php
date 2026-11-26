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
            $newStatus = null;

            if ($action === 'kill') {
                // 1. Send direct kill to Wings
                try {
                    WingsClient::sendPowerAction($server['node'], $server['uuid'], 'kill');
                } catch (\Throwable) {}

                // 2. Local cleanup for any lingering / orphaned host processes for this server
                $this->forceKillServerProcesses($server);

                $newStatus = 'offline';
            } elseif ($action === 'stop' || $action === 'restart') {
                $stopCommand = $this->getSoftwareStopCommand($server);
                $isCustomWings = WingsClient::isCustomWings($server['node']);
                $hasConsoleStop = !empty($stopCommand) && !str_starts_with($stopCommand, '^');

                if ($hasConsoleStop) {
                    // Normal graceful stop command via console (e.g. Minecraft 'stop', Velocity 'end', Source 'quit')
                    try {
                        WingsClient::sendCommands($server['node'], $server['uuid'], [$stopCommand]);
                    } catch (\Throwable) {}

                    try {
                        WingsClient::sendPowerAction($server['node'], $server['uuid'], $action);
                    } catch (\Throwable) {}

                    $newStatus = ($action === 'restart') ? 'starting' : 'stopping';
                } else {
                    // Eggs with NO console stop command (e.g. Python egg with ^C, Node, etc.)
                    if ($isCustomWings) {
                        // In custom Wings (WingsJAVA), sp.stop() calls process.destroy() which kills only
                        // the parent cmd.exe/bash shell and orphans python.exe. In custom Wings, sp.kill()
                        // correctly calls handle.descendants().forEach(destroyForcibly) to kill all children.
                        try {
                            WingsClient::sendPowerAction($server['node'], $server['uuid'], 'kill');
                        } catch (\Throwable) {}

                        // Ensure any local host process for this server is forcibly killed
                        $this->forceKillServerProcesses($server);

                        if ($action === 'restart') {
                            usleep(500000); // 0.5s pause
                            try {
                                WingsClient::sendPowerAction($server['node'], $server['uuid'], 'start');
                            } catch (\Throwable) {}
                            $newStatus = 'starting';
                        } else {
                            $newStatus = 'offline';
                        }
                    } else {
                        // Official Wings (Go + Docker)
                        try {
                            WingsClient::sendPowerAction($server['node'], $server['uuid'], $action);
                        } catch (\Throwable) {}

                        $newStatus = ($action === 'restart') ? 'starting' : 'stopping';
                    }
                }
            } else {
                // 'start'
                WingsClient::sendPowerAction($server['node'], $server['uuid'], 'start');
                $newStatus = 'starting';
            }

            // Update database status immediately
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

            return Response::json(['success' => true, 'status' => $newStatus]);
        } catch (\Throwable $e) {
            // Final fallback: try direct Wings power action if not already tried
            try {
                WingsClient::sendPowerAction($server['node'], $server['uuid'], $action);
                return Response::json(['success' => true]);
            } catch (\Throwable) {
                return Response::json(['error' => 'Unable to reach the node.'], 200);
            }
        }
    }

    /**
     * Forcibly terminates any process running on the host that belongs to this server (UUID or allocated ports).
     */
    private function forceKillServerProcesses(array $server): void
    {
        $uuid = trim((string)($server['uuid'] ?? ''));
        if (empty($uuid)) {
            return;
        }

        // Only perform host process killing if node is local / on this machine
        $node = $server['node'] ?? [];
        $fqdn = strtolower(trim((string)($node['fqdn'] ?? '')));
        $isLocalNode = ($fqdn === '127.0.0.1' || $fqdn === 'localhost' || $fqdn === '::1' || empty($fqdn));
        if (!$isLocalNode) {
            return;
        }

        $isWindows = stripos(PHP_OS, 'WIN') === 0;

        // Collect all allocated ports for this server
        $ports = [];
        try {
            $allocations = Database::query("SELECT `port` FROM `allocations` WHERE `server_id` = :id", ['id' => $server['id']]);
            foreach ($allocations as $a) {
                if (!empty($a['port'])) {
                    $ports[] = (int)$a['port'];
                }
            }
        } catch (\Throwable) {}

        if ($isWindows) {
            $safeUuid = addslashes($uuid);

            // 1. Terminate any processes whose command line contains the server UUID (via WMIC)
            @exec("wmic process where \"CommandLine like '%{$safeUuid}%'\" call terminate 2>NUL");

            // 2. PowerShell CIM process termination fallback
            $psCmd = "Get-CimInstance Win32_Process | Where-Object { \$_.CommandLine -like '*{$safeUuid}*' -or \$_.ExecutablePath -like '*{$safeUuid}*' } | ForEach-Object { Stop-Process -Id \$_.ProcessId -Force -ErrorAction SilentlyContinue }";
            @exec("powershell -NoProfile -NonInteractive -Command \"{$psCmd}\" 2>NUL");

            // 3. Kill any processes bound to the server's ports
            foreach ($ports as $port) {
                $psPortCmd = "Get-NetTCPConnection -LocalPort {$port} -ErrorAction SilentlyContinue | ForEach-Object { Stop-Process -Id \$_.OwningProcess -Force -ErrorAction SilentlyContinue }";
                @exec("powershell -NoProfile -NonInteractive -Command \"{$psPortCmd}\" 2>NUL");
            }
        } else {
            // Linux / Unix
            $safeUuid = escapeshellarg($uuid);
            @exec("pkill -9 -f {$safeUuid} 2>/dev/null");

            foreach ($ports as $port) {
                $portInt = (int)$port;
                @exec("fuser -k -9 {$portInt}/tcp 2>/dev/null");
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

        if (str_contains($ident, 'python') || str_contains($ident, 'node') || str_contains($ident, 'bot')) {
            return '^C';
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
