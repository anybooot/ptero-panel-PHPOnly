<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Admin;

use PDO;
use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Core\View;
use Pterodactyl\Security\Encrypter;

/**
 * Handles Database Host management in Admin panel.
 */
class DatabaseHostHandler
{
    public function index(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $hosts = Database::query(
            "SELECT dh.*, n.`name` as node_name,
                    (SELECT COUNT(*) FROM `databases` d WHERE d.`database_host_id` = dh.`id`) as databases_count
             FROM `database_hosts` dh
             LEFT JOIN `nodes` n ON n.`id` = dh.`node_id`
             ORDER BY dh.`id` ASC"
        );

        $nodes = Database::query("SELECT `id`, `name` FROM `nodes` ORDER BY `name` ASC");

        $html = View::render('admin.databases.index', [
            'user' => $user,
            'hosts' => $hosts,
            'nodes' => $nodes,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'databases',
        ]);

        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        $name = trim((string)$request->input('name', ''));
        $host = trim((string)$request->input('host', '127.0.0.1'));
        $port = (int)$request->input('port', 3306);
        $username = trim((string)$request->input('username', ''));
        $password = (string)$request->input('password', '');
        $nodeId = $request->input('node_id') ? (int)$request->input('node_id') : null;

        if ($name === '' || $host === '' || $username === '') {
            Session::flash('error', 'Name, Host, and Username are required.');
            return Response::redirect('/admin/databases');
        }

        // Test connection
        try {
            $dsn = "mysql:host={$host};port={$port}";
            new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        } catch (\Throwable $e) {
            Session::flash('error', 'Could not connect to database host: ' . $e->getMessage());
            return Response::redirect('/admin/databases');
        }

        try {
            Database::insert('database_hosts', [
                'name' => $name,
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'password' => Encrypter::encryptString($password),
                'node_id' => $nodeId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Session::flash('error', 'Failed to save database host: ' . $e->getMessage());
            return Response::redirect('/admin/databases');
        }

        Session::flash('success', 'Database Host added successfully.');
        return Response::redirect('/admin/databases');
    }

    public function view(Request $request, string $id): Response
    {
        $user = $request->getAttribute('user');
        $host = Database::queryOne("SELECT * FROM `database_hosts` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$host) {
            return Response::redirect('/admin/databases');
        }

        $databases = Database::query(
            "SELECT d.*, s.`name` as server_name, s.`uuidShort`
             FROM `databases` d
             LEFT JOIN `servers` s ON s.`id` = d.`server_id`
             WHERE d.`database_host_id` = :id",
            ['id' => $id]
        );

        $nodes = Database::query("SELECT `id`, `name` FROM `nodes` ORDER BY `name` ASC");

        $html = View::render('admin.databases.view', [
            'user' => $user,
            'host' => $host,
            'databases' => $databases,
            'nodes' => $nodes,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'databases',
        ]);

        return Response::html($html);
    }

    public function update(Request $request, string $id): Response
    {
        $name = trim((string)$request->input('name', ''));
        $host = trim((string)$request->input('host', ''));
        $port = (int)$request->input('port', 3306);
        $username = trim((string)$request->input('username', ''));
        $password = (string)$request->input('password', '');
        $nodeId = $request->input('node_id') ? (int)$request->input('node_id') : null;

        $data = [
            'name' => $name,
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'node_id' => $nodeId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($password !== '') {
            $data['password'] = Encrypter::encryptString($password);
        }

        Database::update('database_hosts', $data, '`id` = :id', ['id' => $id]);
        Session::flash('success', 'Database host updated successfully.');
        return Response::redirect("/admin/databases/view/{$id}");
    }

    public function delete(Request $request, string $id): Response
    {
        $dbCount = (int)(Database::queryOne("SELECT COUNT(*) as c FROM `databases` WHERE `database_host_id` = :id", ['id' => $id])['c'] ?? 0);
        if ($dbCount > 0) {
            Session::flash('error', 'Cannot delete a database host that currently has server databases provisioned on it.');
            return Response::redirect('/admin/databases');
        }

        Database::execute("DELETE FROM `database_hosts` WHERE `id` = :id", ['id' => $id]);
        Session::flash('success', 'Database host removed.');
        return Response::redirect('/admin/databases');
    }
}
