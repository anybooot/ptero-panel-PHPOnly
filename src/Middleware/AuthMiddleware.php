<?php

declare(strict_types=1);

namespace Pterodactyl\Middleware;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;

/**
 * Ensures user is authenticated via session or API token.
 */
class AuthMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        Session::start();
        $userId = Session::get('user_id');

        // Check bearer token for Client API
        if (!$userId) {
            $bearer = $request->getBearerToken();
            if ($bearer && str_starts_with($bearer, 'ptlc_')) {
                // Client API Key check: table api_keys
                $key = Database::queryOne(
                    "SELECT `user_id`, `r_servers` FROM `api_keys` WHERE `token` = :token AND `key_type` = 2 LIMIT 1",
                    ['token' => hash('sha256', $bearer)]
                );
                if ($key) {
                    $userId = (int)$key['user_id'];
                }
            }
        }

        if (!$userId) {
            if ($request->isJson() || str_starts_with($request->getPath(), '/api/')) {
                return Response::json(['errors' => [['code' => 'UnauthorizedHttpException', 'status' => '401', 'detail' => 'You must be logged in to access this resource.']]], 401);
            }
            return Response::redirect('/auth/login');
        }

        $user = Database::queryOne("SELECT * FROM `users` WHERE `id` = :id LIMIT 1", ['id' => $userId]);
        if (!$user) {
            Session::destroy();
            return Response::redirect('/auth/login');
        }

        // Check if 2FA checkpoint is pending
        if (Session::get('2fa_pending') === true && !str_starts_with($request->getPath(), '/auth/')) {
            return Response::redirect('/auth/checkpoint');
        }

        $request->setAttribute('user', $user);
        return $next($request);
    }
}
