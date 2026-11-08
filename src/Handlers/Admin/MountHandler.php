<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Admin;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Core\View;

/**
 * Handles Administrator Mounts management.
 */
class MountHandler
{
    public function index(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $mounts = Database::query(
            "SELECT m.*,
                    (SELECT COUNT(*) FROM `egg_mount` em WHERE em.`mount_id` = m.`id`) as eggs_count,
                    (SELECT COUNT(*) FROM `mount_node` mn WHERE mn.`mount_id` = m.`id`) as nodes_count,
                    (SELECT COUNT(*) FROM `mount_server` ms WHERE ms.`mount_id` = m.`id`) as servers_count
             FROM `mounts` m ORDER BY m.`id` ASC"
        );

        $html = View::render('admin.mounts.index', [
            'user' => $user,
            'mounts' => $mounts,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'mounts',
        ]);

        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        $name = trim((string)$request->input('name', ''));
        $description = (string)$request->input('description', '');
        $source = trim((string)$request->input('source', ''));
        $target = trim((string)$request->input('target', ''));

        if ($name === '' || $source === '' || $target === '') {
            Session::flash('error', 'Name, Source, and Target are required.');
            return Response::redirect('/admin/mounts');
        }

        $id = Database::insert('mounts', [
            'name' => $name,
            'description' => $description,
            'source' => $source,
            'target' => $target,
            'read_only' => $request->boolean('read_only', false) ? 1 : 0,
            'user_mountable' => $request->boolean('user_mountable', false) ? 1 : 0,
        ]);

        Session::flash('success', 'Mount created successfully.');
        return Response::redirect("/admin/mounts/view/{$id}");
    }

    public function view(Request $request, string $id): Response
    {
        $user = $request->getAttribute('user');
        $mount = Database::queryOne("SELECT * FROM `mounts` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$mount) {
            return Response::redirect('/admin/mounts');
        }

        $eggs = Database::query(
            "SELECT e.* FROM `eggs` e JOIN `egg_mount` em ON em.`egg_id` = e.`id` WHERE em.`mount_id` = :id",
            ['id' => $id]
        );
        $allEggs = Database::query("SELECT `id`, `name` FROM `eggs` ORDER BY `name` ASC");

        $nodes = Database::query(
            "SELECT n.* FROM `nodes` n JOIN `mount_node` mn ON mn.`node_id` = n.`id` WHERE mn.`mount_id` = :id",
            ['id' => $id]
        );
        $allNodes = Database::query("SELECT `id`, `name` FROM `nodes` ORDER BY `name` ASC");

        $html = View::render('admin.mounts.view', [
            'user' => $user,
            'mount' => $mount,
            'eggs' => $eggs,
            'allEggs' => $allEggs,
            'nodes' => $nodes,
            'allNodes' => $allNodes,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'mounts',
        ]);

        return Response::html($html);
    }

    public function update(Request $request, string $id): Response
    {
        $name = trim((string)$request->input('name', ''));
        $description = (string)$request->input('description', '');
        $source = trim((string)$request->input('source', ''));
        $target = trim((string)$request->input('target', ''));

        Database::update('mounts', [
            'name' => $name,
            'description' => $description,
            'source' => $source,
            'target' => $target,
            'read_only' => $request->boolean('read_only', false) ? 1 : 0,
            'user_mountable' => $request->boolean('user_mountable', false) ? 1 : 0,
        ], '`id` = :id', ['id' => $id]);

        Session::flash('success', 'Mount updated.');
        return Response::redirect("/admin/mounts/view/{$id}");
    }

    public function addEggs(Request $request, string $id): Response
    {
        $eggIds = (array)$request->input('eggs', []);
        foreach ($eggIds as $eggId) {
            $exists = Database::queryOne(
                "SELECT 1 FROM `egg_mount` WHERE `mount_id` = :m AND `egg_id` = :e",
                ['m' => $id, 'e' => $eggId]
            );
            if (!$exists) {
                Database::insert('egg_mount', ['mount_id' => $id, 'egg_id' => $eggId]);
            }
        }
        Session::flash('success', 'Eggs attached.');
        return Response::redirect("/admin/mounts/view/{$id}");
    }

    public function deleteEgg(Request $request, string $id, string $eggId): Response
    {
        Database::execute("DELETE FROM `egg_mount` WHERE `mount_id` = :m AND `egg_id` = :e", ['m' => $id, 'e' => $eggId]);
        Session::flash('success', 'Egg detached.');
        return Response::redirect("/admin/mounts/view/{$id}");
    }

    public function addNodes(Request $request, string $id): Response
    {
        $nodeIds = (array)$request->input('nodes', []);
        foreach ($nodeIds as $nodeId) {
            $exists = Database::queryOne(
                "SELECT 1 FROM `mount_node` WHERE `mount_id` = :m AND `node_id` = :n",
                ['m' => $id, 'n' => $nodeId]
            );
            if (!$exists) {
                Database::insert('mount_node', ['mount_id' => $id, 'node_id' => $nodeId]);
            }
        }
        Session::flash('success', 'Nodes attached.');
        return Response::redirect("/admin/mounts/view/{$id}");
    }

    public function deleteNode(Request $request, string $id, string $nodeId): Response
    {
        Database::execute("DELETE FROM `mount_node` WHERE `mount_id` = :m AND `node_id` = :n", ['m' => $id, 'n' => $nodeId]);
        Session::flash('success', 'Node detached.');
        return Response::redirect("/admin/mounts/view/{$id}");
    }

    public function delete(Request $request, string $id): Response
    {
        Database::execute("DELETE FROM `egg_mount` WHERE `mount_id` = :id", ['id' => $id]);
        Database::execute("DELETE FROM `mount_node` WHERE `mount_id` = :id", ['id' => $id]);
        Database::execute("DELETE FROM `mount_server` WHERE `mount_id` = :id", ['id' => $id]);
        Database::execute("DELETE FROM `mounts` WHERE `id` = :id", ['id' => $id]);

        Session::flash('success', 'Mount deleted.');
        return Response::redirect('/admin/mounts');
    }
}
