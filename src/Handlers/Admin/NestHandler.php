<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Admin;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Core\View;

/**
 * Handles Administrator Nest management.
 */
class NestHandler
{
    public function index(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $nests = Database::query(
            "SELECT n.*,
                    (SELECT COUNT(*) FROM `eggs` e WHERE e.`nest_id` = n.`id`) as eggs_count,
                    (SELECT COUNT(*) FROM `servers` s WHERE s.`nest_id` = n.`id`) as servers_count
             FROM `nests` n ORDER BY n.`id` ASC"
        );

        $html = View::render('admin.nests.index', [
            'user' => $user,
            'nests' => $nests,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'nests',
        ]);

        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        $name = trim((string)$request->input('name', ''));
        $description = (string)$request->input('description', '');

        if ($name === '') {
            Session::flash('error', 'Nest name is required.');
            return Response::redirect('/admin/nests');
        }

        $id = Database::insert('nests', [
            'uuid' => bin2hex(random_bytes(16)),
            'author' => 'support@pterodactyl.io',
            'name' => $name,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Session::flash('success', 'Nest created successfully.');
        return Response::redirect("/admin/nests/view/{$id}");
    }

    public function view(Request $request, string $id): Response
    {
        $user = $request->getAttribute('user');
        $nest = Database::queryOne("SELECT * FROM `nests` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$nest) {
            return Response::redirect('/admin/nests');
        }

        $eggs = Database::query(
            "SELECT e.*, (SELECT COUNT(*) FROM `servers` s WHERE s.`egg_id` = e.`id`) as servers_count
             FROM `eggs` e WHERE e.`nest_id` = :id ORDER BY e.`id` ASC",
            ['id' => $id]
        );

        $html = View::render('admin.nests.view', [
            'user' => $user,
            'nest' => $nest,
            'eggs' => $eggs,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'nests',
        ]);

        return Response::html($html);
    }

    public function update(Request $request, string $id): Response
    {
        $name = trim((string)$request->input('name', ''));
        $description = (string)$request->input('description', '');

        if ($name === '') {
            Session::flash('error', 'Nest name is required.');
            return Response::redirect("/admin/nests/view/{$id}");
        }

        Database::update('nests', [
            'name' => $name,
            'description' => $description,
            'updated_at' => date('Y-m-d H:i:s'),
        ], '`id` = :id', ['id' => $id]);

        Session::flash('success', 'Nest updated.');
        return Response::redirect("/admin/nests/view/{$id}");
    }

    public function delete(Request $request, string $id): Response
    {
        $eggCount = (int)(Database::queryOne("SELECT COUNT(*) as c FROM `eggs` WHERE `nest_id` = :id", ['id' => $id])['c'] ?? 0);
        if ($eggCount > 0) {
            Session::flash('error', 'Cannot delete a nest that contains eggs.');
            return Response::redirect('/admin/nests');
        }

        Database::execute("DELETE FROM `nests` WHERE `id` = :id", ['id' => $id]);
        Session::flash('success', 'Nest deleted.');
        return Response::redirect('/admin/nests');
    }
}
