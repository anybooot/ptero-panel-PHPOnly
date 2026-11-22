<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Admin;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\View;

/**
 * Handles the Administrator Panel Dashboard / Overview.
 */
class OverviewHandler
{
    public function index(Request $request): Response
    {
        $user = $request->getAttribute('user');

        $serversCount = (int)(Database::queryOne("SELECT COUNT(*) as c FROM `servers`")['c'] ?? 0);
        $nodesCount = (int)(Database::queryOne("SELECT COUNT(*) as c FROM `nodes`")['c'] ?? 0);
        $usersCount = (int)(Database::queryOne("SELECT COUNT(*) as c FROM `users`")['c'] ?? 0);
        $allocationsCount = (int)(Database::queryOne("SELECT COUNT(*) as c FROM `allocations`")['c'] ?? 0);

        $html = View::render('admin.index', [
            'user' => $user,
            'serversCount' => $serversCount,
            'nodesCount' => $nodesCount,
            'usersCount' => $usersCount,
            'allocationsCount' => $allocationsCount,
            'activeAdminTab' => 'overview',
        ]);

        return Response::html($html);
    }
}
