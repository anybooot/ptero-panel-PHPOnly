<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Admin;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Core\View;

/**
 * Handles Admin Location management.
 */
class LocationHandler
{
    public function index(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $locations = Database::query(
            "SELECT l.*,
                    (SELECT COUNT(*) FROM `nodes` n WHERE n.`location_id` = l.`id`) as nodes_count,
                    (SELECT COUNT(*) FROM `servers` s JOIN `nodes` n ON n.`id` = s.`node_id` WHERE n.`location_id` = l.`id`) as servers_count
             FROM `locations` l ORDER BY l.`id` ASC"
        );

        $html = View::render('admin.locations.index', [
            'user' => $user,
            'locations' => $locations,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'locations',
        ]);

        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        $short = trim((string)$request->input('short', ''));
        $long = trim((string)$request->input('long', ''));

        if ($short === '') {
            Session::flash('error', 'Short code is required.');
            return Response::redirect('/admin/locations');
        }

        Database::insert('locations', [
            'short' => $short,
            'long' => $long,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Session::flash('success', 'Location created successfully.');
        return Response::redirect('/admin/locations');
    }

    public function view(Request $request, string $id): Response
    {
        $user = $request->getAttribute('user');
        $location = Database::queryOne("SELECT * FROM `locations` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$location) {
            return Response::redirect('/admin/locations');
        }

        $nodes = Database::query("SELECT * FROM `nodes` WHERE `location_id` = :id", ['id' => $id]);

        $html = View::render('admin.locations.view', [
            'user' => $user,
            'location' => $location,
            'nodes' => $nodes,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'locations',
        ]);

        return Response::html($html);
    }

    public function update(Request $request, string $id): Response
    {
        $short = trim((string)$request->input('short', ''));
        $long = trim((string)$request->input('long', ''));

        if ($short === '') {
            Session::flash('error', 'Short code is required.');
            return Response::redirect("/admin/locations/view/{$id}");
        }

        Database::update('locations', [
            'short' => $short,
            'long' => $long,
            'updated_at' => date('Y-m-d H:i:s'),
        ], '`id` = :id', ['id' => $id]);

        Session::flash('success', 'Location updated successfully.');
        return Response::redirect("/admin/locations/view/{$id}");
    }

    public function delete(Request $request, string $id): Response
    {
        $nodesCount = (int)(Database::queryOne("SELECT COUNT(*) as c FROM `nodes` WHERE `location_id` = :id", ['id' => $id])['c'] ?? 0);
        if ($nodesCount > 0) {
            Session::flash('error', 'Cannot delete a location that has nodes attached to it.');
            return Response::redirect('/admin/locations');
        }

        Database::execute("DELETE FROM `locations` WHERE `id` = :id", ['id' => $id]);
        Session::flash('success', 'Location deleted.');
        return Response::redirect('/admin/locations');
    }
}
