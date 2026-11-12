<?php

declare(strict_types=1);

namespace Pterodactyl\Middleware;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Security\Gate;

/**
 * Resolves server parameter (ID, short UUID, or full UUID) and verifies user access.
 */
class ServerAccessMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $serverParam = $request->getAttribute('server')
            ?? $request->getAttribute('id')
            ?? $request->getAttribute('uuid');

        if (!$serverParam) {
            return Response::json(['error' => 'Server identifier missing.'], 400);
        }

        $server = Database::queryOne(
            "SELECT * FROM `servers` WHERE `uuid` = :param1 OR `uuidShort` = :param2 OR `id` = :param3 LIMIT 1",
            ['param1' => $serverParam, 'param2' => $serverParam, 'param3' => is_numeric($serverParam) ? (int)$serverParam : 0]
        );

        if (!$server) {
            if ($request->isJson() || str_starts_with($request->getPath(), '/api/')) {
                return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'Server not found.']]], 404);
            }
            return Response::html(\Pterodactyl\Core\ErrorRenderer::render(404, 'The requested server does not exist or has been deleted.', 'Server Not Found'), 404);
        }

        $user = $request->getAttribute('user');
        if ($user && !Gate::hasServerAccess($user, $server)) {
            if ($request->isJson() || str_starts_with($request->getPath(), '/api/')) {
                return Response::json(['errors' => [['code' => 'AccessDeniedHttpException', 'status' => '403', 'detail' => 'You do not have permission to access this server.']]], 403);
            }
            return Response::html(\Pterodactyl\Core\ErrorRenderer::render(403, 'You do not have permission to view or manage this server.', 'Access Denied'), 403);
        }

        // Attach Node
        $node = Database::queryOne("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1", ['id' => $server['node_id']]);
        $server['node'] = $node;

        $request->setAttribute('server', $server);
        return $next($request);
    }
}
