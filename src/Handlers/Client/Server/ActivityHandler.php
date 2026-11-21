<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Client\Server;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\View;
use Pterodactyl\Security\Gate;

/**
 * Handles Server Activity / Audit Log timeline.
 */
class ActivityHandler
{
    public function index(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'activity.read')) {
            if ($request->isJson() || str_starts_with($request->getPath(), '/api/')) {
                return Response::json(['errors' => [['code' => 'AccessDeniedHttpException', 'status' => '403', 'detail' => 'You do not have permission to view server activity.']]], 403);
            }
            return Response::html(\Pterodactyl\Core\ErrorRenderer::render(403, 'You do not have permission to view server activity.', 'Access Denied'), 403);
        }

        $perPage = 50;
        $page = max(1, (int)$request->input('page', 1));

        $countRow = Database::queryOne(
            "SELECT COUNT(DISTINCT al.`id`) as total
             FROM `activity_log_subjects` als
             JOIN `activity_logs` al ON al.`id` = als.`activity_log_id`
             WHERE als.`subject_id` = :server_id AND als.`subject_type` IN ('server', 'App\\\\Models\\\\Server')",
            ['server_id' => $server['id']]
        );
        $total = (int)($countRow['total'] ?? 0);
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $logs = Database::query(
            "SELECT al.`event`, al.`ip`, al.`properties`, al.`timestamp`,
                    u.`username` as actor_username, u.`email` as actor_email
             FROM `activity_log_subjects` als
             JOIN `activity_logs` al ON al.`id` = als.`activity_log_id`
             LEFT JOIN `users` u ON u.`id` = al.`actor_id` AND (al.`actor_type` IN ('user', 'App\\\\Models\\\\User') OR al.`actor_type` IS NULL)
             WHERE als.`subject_id` = :server_id AND als.`subject_type` IN ('server', 'App\\\\Models\\\\Server')
             ORDER BY al.`timestamp` DESC LIMIT {$perPage} OFFSET {$offset}",
            ['server_id' => $server['id']]
        );

        if ($request->isJson()) {
            return Response::json([
                'data' => $logs,
                'meta' => [
                    'pagination' => [
                        'total' => $total,
                        'count' => count($logs),
                        'per_page' => $perPage,
                        'current_page' => $page,
                        'total_pages' => $totalPages,
                    ]
                ]
            ]);
        }

        $html = View::render('server.activity', [
            'server' => $server,
            'node' => $server['node'],
            'user' => $user,
            'logs' => $logs,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'perPage' => $perPage,
            'activeTab' => 'activity',
        ]);

        return Response::html($html);
    }
}
