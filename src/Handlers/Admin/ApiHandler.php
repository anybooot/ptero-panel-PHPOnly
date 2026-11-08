<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Admin;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Core\View;

/**
 * Handles Administrator Application API Keys management.
 */
class ApiHandler
{
    public function index(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $keys = Database::query(
            "SELECT `id`, `identifier`, `memo`, `allowed_ips`, `last_used_at`, `created_at`
             FROM `api_keys` WHERE `key_type` = 1 ORDER BY `created_at` DESC"
        );

        $html = View::render('admin.api.index', [
            'user' => $user,
            'keys' => $keys,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'newKey' => Session::getFlash('newKey'),
            'activeAdminTab' => 'api',
        ]);

        return Response::html($html);
    }

    public function createForm(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $html = View::render('admin.api.new', [
            'user' => $user,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'api',
        ]);
        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $memo = trim((string)$request->input('memo', ''));
        $allowedIps = trim((string)$request->input('allowed_ips', ''));

        if ($memo === '') {
            Session::flash('error', 'Description (memo) is required.');
            return Response::redirect('/admin/api/new');
        }

        $identifier = bin2hex(random_bytes(8));
        $secretToken = bin2hex(random_bytes(24));
        $fullKey = "ptla_{$identifier}_{$secretToken}";

        Database::insert('api_keys', [
            'user_id' => $user['id'],
            'key_type' => 1, // 1 = Application API Key
            'identifier' => $identifier,
            'token' => hash('sha256', $fullKey),
            'memo' => $memo,
            'allowed_ips' => !empty($allowedIps) ? json_encode(explode("\n", str_replace("\r", "", $allowedIps))) : null,
            'r_servers' => (int)$request->input('r_servers', 3),
            'r_nodes' => (int)$request->input('r_nodes', 3),
            'r_allocations' => (int)$request->input('r_allocations', 3),
            'r_users' => (int)$request->input('r_users', 3),
            'r_locations' => (int)$request->input('r_locations', 3),
            'r_nests' => (int)$request->input('r_nests', 3),
            'r_eggs' => (int)$request->input('r_eggs', 3),
            'r_database_hosts' => (int)$request->input('r_database_hosts', 3),
            'r_server_databases' => (int)$request->input('r_server_databases', 3),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Session::flash('newKey', $fullKey);
        Session::flash('success', 'Application API Key created. Please copy it immediately as it will not be displayed again.');
        return Response::redirect('/admin/api');
    }

    public function delete(Request $request, string $identifier): Response
    {
        Database::execute(
            "DELETE FROM `api_keys` WHERE `identifier` = :id AND `key_type` = 1",
            ['id' => $identifier]
        );

        Session::flash('success', 'API key revoked.');
        return Response::redirect('/admin/api');
    }
}
