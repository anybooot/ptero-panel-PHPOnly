<?php

declare(strict_types=1);

namespace Pterodactyl\Middleware;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Security\Encrypter;

/**
 * Authenticates requests coming directly from Wings daemon to /api/remote/*.
 */
class DaemonAuthMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $bearer = $request->getBearerToken();
        if (!$bearer) {
            return Response::json(['error' => 'Access to this endpoint must include an Authorization header.'], 401)
                ->withHeader('WWW-Authenticate', 'Bearer');
        }

        $parts = explode('.', $bearer, 2);
        if (count($parts) !== 2 || empty($parts[0]) || empty($parts[1])) {
            return Response::json(['error' => 'The Authorization header provided was not in a valid format.'], 400);
        }

        $tokenId = $parts[0];
        $tokenSecret = $parts[1];

        $node = Database::queryOne(
            "SELECT * FROM `nodes` WHERE `daemon_token_id` = :token_id LIMIT 1",
            ['token_id' => $tokenId]
        );

        if (!$node) {
            return Response::json(['error' => 'You are not authorized to access this resource.'], 403);
        }

        try {
            $decryptedToken = Encrypter::decryptString($node['daemon_token']);
            if (!hash_equals($decryptedToken, $tokenSecret)) {
                return Response::json(['error' => 'You are not authorized to access this resource.'], 403);
            }
        } catch (\Throwable) {
            return Response::json(['error' => 'You are not authorized to access this resource.'], 403);
        }

        $request->setAttribute('node', $node);
        return $next($request);
    }
}
