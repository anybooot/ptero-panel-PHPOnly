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
            return Response::html('<h1>403 Forbidden</h1><p>You do not have permission to view server activity.</p>', 403);
        }

        $logs = Database::query(
            "SELECT al.`event`, al.`ip`, al.`properties`, al.`timestamp`,
                    u.`username` as actor_username, u.`email` as actor_email
             FROM `activity_log_subjects` als
             JOIN `activity_logs` al ON al.`id` = als.`activity_log_id`
             LEFT JOIN `users` u ON u.`id` = al.`actor_id` AND al.`actor_type` = 'user'
             WHERE als.`subject_id` = :server_id AND als.`subject_type` = 'server'
             ORDER BY al.`timestamp` DESC LIMIT 50",
            ['server_id' => $server['id']]
        );

        if ($request->isJson()) {
            return Response::json(['data' => $logs]);
        }

        $html = View::render('server.activity', [
            'server' => $server,
            'node' => $server['node'],
            'user' => $user,
            'logs' => $logs,
            'activeTab' => 'activity',
        ]);

        return Response::html($html);
    }
}
