<?php

declare(strict_types=1);

namespace Pterodactyl\Middleware;

use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Security\Gate;

/**
 * Ensures authenticated user is a Root Administrator.
 */
class AdminMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $user = $request->getAttribute('user');
        if (!Gate::isRootAdmin($user) || (int)($user['root_admin'] ?? 0) !== 1) {
            if ($user && isset($user['id'])) {
                \Pterodactyl\Core\ActivityLogger::log('admin:unauthorized_access', $user, ['path' => $request->getPath(), 'ip' => $request->getClientIp()], $request);
            }
            if ($request->isJson() || str_starts_with($request->getPath(), '/api/')) {
                return Response::json(['errors' => [['code' => 'AccessDeniedHttpException', 'status' => '403', 'detail' => 'You do not have administrative privileges.']]], 403);
            }
            return Response::html(\Pterodactyl\Core\ErrorRenderer::render(403, 'You do not have administrative privileges to access this area.', 'Access Denied'), 403);
        }

        return $next($request);
    }
}
