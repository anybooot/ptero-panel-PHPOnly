<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Client;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Core\View;
use Pterodactyl\Security\Gate;

/**
 * Handles the main dashboard and server listing.
 */
class DashboardHandler
{
    public function index(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $isAdmin = Gate::isRootAdmin($user);
        $search = trim((string)$request->input('search', ''));

        // Handle show_all toggle for administrators
        $showAllParam = $request->input('show_all');
        if ($isAdmin && $showAllParam !== null) {
            $showAll = $showAllParam === '1' || $showAllParam === 'true';
            Session::set('dash_show_all', $showAll);
        } elseif ($isAdmin) {
            $showAll = (bool)Session::get('dash_show_all', false);
        } else {
            $showAll = false;
        }

        $query = "SELECT s.*, n.`name` as node_name, n.`fqdn` as node_fqdn,
                         a.`ip` as allocation_ip, a.`port` as allocation_port, a.`ip_alias` as allocation_alias,
                         u.`username` as owner_username, u.`email` as owner_email
                  FROM `servers` s
                  LEFT JOIN `nodes` n ON n.`id` = s.`node_id`
                  LEFT JOIN `allocations` a ON a.`id` = s.`allocation_id`
                  LEFT JOIN `users` u ON u.`id` = s.`owner_id`";

        $params = [];
        $where = [];

        // By default (or for non-admins), only show accessible/owned servers.
        // If admin toggles switch, show all created servers on the panel.
        if (!$isAdmin || !$showAll) {
            $where[] = "(s.`owner_id` = :user_id OR s.`id` IN (SELECT `server_id` FROM `subusers` WHERE `user_id` = :subuser_id))";
            $params['user_id'] = $user['id'];
            $params['subuser_id'] = $user['id'];
        }

        if ($search !== '') {
            $where[] = "(s.`name` LIKE :search OR s.`uuid` LIKE :search2 OR s.`uuidShort` LIKE :search3)";
            $params['search'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
            $params['search3'] = "%{$search}%";
        }

        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }

        $query .= " ORDER BY s.`name` ASC";
        $servers = Database::query($query, $params);

        if ($request->isJson()) {
            return Response::json(['data' => $servers]);
        }

        $html = View::render('dashboard.index', [
            'user' => $user,
            'servers' => $servers,
            'search' => $search,
            'isAdmin' => $isAdmin,
            'showAll' => $showAll,
        ]);

        return Response::html($html);
    }
}
