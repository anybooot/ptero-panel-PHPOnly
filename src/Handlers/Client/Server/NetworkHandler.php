<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Client\Server;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\View;
use Pterodactyl\Security\Gate;
use Pterodactyl\Wings\WingsClient;

/**
 * Handles Server Network Allocations.
 */
class NetworkHandler
{
    public function index(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'allocation.read')) {
            return Gate::denyResponse($request, $server, $user, 'allocation.read', 'You do not have permission to view network allocations for this server.');
        }

        $allocations = Database::query(
            "SELECT * FROM `allocations` WHERE `server_id` = :server_id ORDER BY `port` ASC",
            ['server_id' => $server['id']]
        );

        if ($request->isJson()) {
            return Response::json(['data' => $allocations]);
        }

        $allocationLimit = (int)($server['allocation_limit'] ?? 0);
        $currentAllocCount = count($allocations);
        $freeNodeAllocCount = (int)(Database::queryOne(
            "SELECT COUNT(*) as c FROM `allocations` WHERE `node_id` = :node_id AND `server_id` IS NULL",
            ['node_id' => $server['node_id']]
        )['c'] ?? 0);

        $html = View::render('server.network', [
            'server' => $server,
            'node' => $server['node'],
            'user' => $user,
            'allocations' => $allocations,
            'allocationLimit' => $allocationLimit,
            'currentAllocCount' => $currentAllocCount,
            'freeNodeAllocCount' => $freeNodeAllocCount,
            'activeTab' => 'network',
        ]);

        return Response::html($html);
    }

    public function assign(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'allocation.create')) {
            return Response::json(['error' => 'You do not have permission to assign new port allocations.'], 403);
        }

        $limit = (int)($server['allocation_limit'] ?? 0);
        $countRow = Database::queryOne("SELECT COUNT(*) as count FROM `allocations` WHERE `server_id` = :id", ['id' => $server['id']]);
        $currentCount = (int)($countRow['count'] ?? 0);

        if ($limit > 0 && $currentCount >= $limit) {
            return Response::json([
                'error' => "The allocation limit for this server has been reached ({$currentCount}/{$limit}). You cannot create additional allocations."
            ], 400);
        }

        // Find an unassigned allocation on the same node
        $alloc = Database::queryOne(
            "SELECT * FROM `allocations` WHERE `node_id` = :node_id AND `server_id` IS NULL LIMIT 1",
            ['node_id' => $server['node_id']]
        );
        if (!$alloc) {
            return Response::json([
                'error' => 'No free ports are available on this node. Contact an administrator.'
            ], 400);
        }

        Database::execute(
            "UPDATE `allocations` SET `server_id` = :server_id WHERE `id` = :id",
            ['server_id' => $server['id'], 'id' => $alloc['id']]
        );

        \Pterodactyl\Core\ActivityLogger::log('server:allocation.create', $user, ['allocation' => "{$alloc['ip']}:{$alloc['port']}"], $request, $server);

        try {
            WingsClient::syncServer($server['node'], $server['uuid']);
        } catch (\Throwable) {
        }

        return Response::json([
            'id' => $alloc['id'],
            'ip' => $alloc['ip'],
            'port' => (int)$alloc['port'],
            'alias' => $alloc['ip_alias'],
        ]);
    }

    public function setPrimary(Request $request, string $allocationId): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'allocation.update')) {
            return Response::json(['error' => 'You do not have permission to set the primary allocation.'], 403);
        }

        $alloc = Database::queryOne(
            "SELECT * FROM `allocations` WHERE `id` = :id AND `server_id` = :server_id LIMIT 1",
            ['id' => $allocationId, 'server_id' => $server['id']]
        );
        if (!$alloc) {
            return Response::json(['error' => 'The specified allocation does not exist or does not belong to this server.'], 404);
        }

        Database::update('servers', ['allocation_id' => $alloc['id']], '`id` = :id', ['id' => $server['id']]);

        \Pterodactyl\Core\ActivityLogger::log('server:allocation.primary', $user, ['allocation' => "{$alloc['ip']}:{$alloc['port']}"], $request, $server);

        try {
            WingsClient::syncServer($server['node'], $server['uuid']);
        } catch (\Throwable) {
        }

        return Response::json(['success' => true]);
    }

    public function delete(Request $request, string $allocationId): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'allocation.delete')) {
            return Response::json(['error' => 'You do not have permission to delete allocations.'], 403);
        }

        if (((int)$server['allocation_id']) === (int)$allocationId) {
            return Response::json(['error' => 'You cannot remove the primary allocation of the server.'], 400);
        }

        $alloc = Database::queryOne(
            "SELECT * FROM `allocations` WHERE `id` = :id AND `server_id` = :server_id LIMIT 1",
            ['id' => $allocationId, 'server_id' => $server['id']]
        );
        if (!$alloc) {
            return Response::json(['error' => 'The allocation was not found.'], 404);
        }

        Database::execute(
            "UPDATE `allocations` SET `server_id` = NULL, `notes` = NULL WHERE `id` = :id",
            ['id' => $allocationId]
        );

        \Pterodactyl\Core\ActivityLogger::log('server:allocation.delete', $user, ['allocation' => "{$alloc['ip']}:{$alloc['port']}"], $request, $server);

        try {
            WingsClient::syncServer($server['node'], $server['uuid']);
        } catch (\Throwable) {
        }

        return Response::noContent();
    }
}
