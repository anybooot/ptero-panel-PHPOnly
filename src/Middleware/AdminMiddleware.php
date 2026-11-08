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
        if (!Gate::isRootAdmin($user)) {
            if ($request->isJson() || str_starts_with($request->getPath(), '/api/')) {
                return Response::json(['errors' => [['code' => 'AccessDeniedHttpException', 'status' => '403', 'detail' => 'You do not have administrative privileges.']]], 403);
            }
            return Response::html('<h1>403 Forbidden</h1><p>You do not have permission to access the administrator panel.</p>', 403);
        }

        return $next($request);
    }
}
