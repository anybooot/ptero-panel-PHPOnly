<?php

declare(strict_types=1);

namespace Pterodactyl\Middleware;

use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;

/**
 * Validates CSRF token on state-changing requests.
 */
class CsrfMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $method = $request->getMethod();
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        // Skip CSRF check for API endpoints with Authorization header
        if (str_starts_with($request->getPath(), '/api/')) {
            return $next($request);
        }

        $token = $request->input('_csrf_token')
            ?? $request->getHeader('x-csrf-token')
            ?? $request->getHeader('x-xsrf-token');

        if (!Session::validateCsrfToken($token)) {
            if ($request->isJson() || $request->isAjax()) {
                return Response::json(['error' => 'CSRF token mismatch. Please refresh and try again.'], 419);
            }
            return Response::html(\Pterodactyl\Core\ErrorRenderer::render(419, 'CSRF security token mismatch or session expired. Please refresh the page and try again.', 'Page Expired'), 419);
        }

        return $next($request);
    }
}
