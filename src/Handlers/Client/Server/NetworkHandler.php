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
            if ($request->isJson() || str_starts_with($request->getPath(), '/api/')) {
                return Response::json(['errors' => [['code' => 'AccessDeniedHttpException', 'status' => '403', 'detail' => 'You do not have permission to view allocations.']]], 403);
            }
            return Response::html(\Pterodactyl\Core\ErrorRenderer::render(403, 'You do not have permission to view allocations.', 'Access Denied'), 403);
        }

        $allocations = Database::query(
            "SELECT * FROM `allocations` WHERE `server_id` = :server_id ORDER BY `port` ASC",
            ['server_id' => $server['id']]
        );

        if ($request->isJson()) {
            return Response::json(['data' => $allocations]);
        }

        $html = View::render('server.network', [
            'server' => $server,
            'node' => $server['node'],
            'user' => $user,
            'allocations' => $allocations,
            'activeTab' => 'network',
        ]);

        return Response::html($html);
    }

    public function assign(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'allocation.create')) {
            return Response::json(['error' => 'You do not have permission to assign allocations.'], 403);
        }

        $limit = (int)($server['allocation_limit'] ?? 0);
        $countRow = Database::queryOne("SELECT COUNT(*) as count FROM `allocations` WHERE `server_id` = :id", ['id' => $server['id']]);
        if ($limit > 0 && ($countRow['count'] ?? 0) >= $limit) {
            return Response::json(['error' => 'The allocation limit for this server has been reached.'], 400);
        }

        // Find an unassigned allocation on the same node
        $alloc = Database::queryOne(
            "SELECT * FROM `allocations` WHERE `node_id` = :node_id AND `server_id` IS NULL LIMIT 1",
            ['node_id' => $server['node_id']]
        );
        if (!$alloc) {
            return Response::json(['error' => 'No free allocations are available on this node.'], 400);
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
            return Response::json(['error' => 'You do not have permission to update allocations.'], 403);
        }

        $alloc = Database::queryOne(
            "SELECT * FROM `allocations` WHERE `id` = :id AND `server_id` = :server_id LIMIT 1",
            ['id' => $allocationId, 'server_id' => $server['id']]
        );
        if (!$alloc) {
            return Response::json(['error' => 'Allocation not found on this server.'], 404);
        }

        Database::execute("UPDATE `servers` SET `allocation_id` = :alloc_id WHERE `id` = :id", [
            'alloc_id' => $alloc['id'],
            'id' => $server['id'],
        ]);

        \Pterodactyl\Core\ActivityLogger::log('server:allocation.primary', $user, ['allocation' => "{$alloc['ip']}:{$alloc['port']}"], $request, $server);

        try {
            WingsClient::syncServer($server['node'], $server['uuid']);
        } catch (\Throwable) {
        }

        return Response::noContent();
    }

    public function setNotes(Request $request, string $allocationId): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'allocation.update')) {
            return Response::json(['error' => 'You do not have permission to update allocations.'], 403);
        }

        $notes = trim((string)$request->input('notes', ''));
        Database::execute(
            "UPDATE `allocations` SET `notes` = :notes WHERE `id` = :id AND `server_id` = :server_id",
            ['notes' => $notes, 'id' => $allocationId, 'server_id' => $server['id']]
        );

        return Response::noContent();
    }

    public function delete(Request $request, string $allocationId): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'allocation.delete')) {
            return Response::json(['error' => 'You do not have permission to delete allocations.'], 403);
        }

        if (((int)$server['allocation_id']) === ((int)$allocationId)) {
            return Response::json(['error' => 'Cannot remove the primary allocation for this server.'], 400);
        }

        Database::execute(
            "UPDATE `allocations` SET `server_id` = NULL, `notes` = NULL WHERE `id` = :id AND `server_id` = :server_id",
            ['id' => $allocationId, 'server_id' => $server['id']]
        );

        \Pterodactyl\Core\ActivityLogger::log('server:allocation.delete', $user, ['allocation_id' => $allocationId], $request, $server);

        try {
            WingsClient::syncServer($server['node'], $server['uuid']);
        } catch (\Throwable) {
        }

        return Response::noContent();
    }
}
