<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Admin;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Core\View;

/**
 * Handles Administrator User Management.
 */
class UserHandler
{
    public function index(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $search = trim((string)$request->input('search', ''));

        $query = "SELECT u.*, (SELECT COUNT(*) FROM `servers` s WHERE s.`owner_id` = u.`id`) as servers_count
                  FROM `users` u";
        $params = [];
        if ($search !== '') {
            $query .= " WHERE u.`username` LIKE :s OR u.`email` LIKE :s2 OR u.`name_first` LIKE :s3 OR u.`name_last` LIKE :s4";
            $params['s'] = "%{$search}%";
            $params['s2'] = "%{$search}%";
            $params['s3'] = "%{$search}%";
            $params['s4'] = "%{$search}%";
        }
        $query .= " ORDER BY u.`id` ASC";

        $users = Database::query($query, $params);

        $html = View::render('admin.users.index', [
            'user' => $user,
            'users' => $users,
            'search' => $search,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'users',
        ]);

        return Response::html($html);
    }

    public function createForm(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $html = View::render('admin.users.new', [
            'user' => $user,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'users',
        ]);
        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        $email = trim((string)$request->input('email', ''));
        $username = trim((string)$request->input('username', ''));
        $nameFirst = trim((string)$request->input('name_first', ''));
        $nameLast = trim((string)$request->input('name_last', ''));
        $password = (string)$request->input('password', '');
        $rootAdmin = $request->boolean('root_admin', false) ? 1 : 0;

        if ($email === '' || $username === '' || $password === '') {
            Session::flash('error', 'Email, Username, and Password are required.');
            return Response::redirect('/admin/users/new');
        }

        $existing = Database::queryOne(
            "SELECT `id` FROM `users` WHERE `email` = :email OR `username` = :username LIMIT 1",
            ['email' => $email, 'username' => $username]
        );
        if ($existing) {
            Session::flash('error', 'A user with that email or username already exists.');
            return Response::redirect('/admin/users/new');
        }

        $uuid = bin2hex(random_bytes(16));
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $id = Database::insert('users', [
            'uuid' => $uuid,
            'email' => $email,
            'username' => $username,
            'name_first' => $nameFirst !== '' ? $nameFirst : $username,
            'name_last' => $nameLast !== '' ? $nameLast : '',
            'password' => $hashedPassword,
            'root_admin' => $rootAdmin,
            'language' => 'en',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Session::flash('success', 'User created successfully.');
        return Response::redirect("/admin/users/view/{$id}");
    }

    public function view(Request $request, string $id): Response
    {
        $user = $request->getAttribute('user');
        $targetUser = Database::queryOne("SELECT * FROM `users` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$targetUser) {
            return Response::redirect('/admin/users');
        }

        $servers = Database::query(
            "SELECT s.*, n.`name` as node_name FROM `servers` s JOIN `nodes` n ON n.`id` = s.`node_id` WHERE s.`owner_id` = :id",
            ['id' => $id]
        );

        $html = View::render('admin.users.view', [
            'user' => $user,
            'targetUser' => $targetUser,
            'servers' => $servers,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'users',
        ]);

        return Response::html($html);
    }

    public function update(Request $request, string $id): Response
    {
        $targetUser = Database::queryOne("SELECT * FROM `users` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$targetUser) {
            return Response::redirect('/admin/users');
        }

        $email = trim((string)$request->input('email', $targetUser['email']));
        $username = trim((string)$request->input('username', $targetUser['username']));
        $nameFirst = trim((string)$request->input('name_first', $targetUser['name_first']));
        $nameLast = trim((string)$request->input('name_last', $targetUser['name_last']));
        $password = (string)$request->input('password', '');
        $rootAdmin = $request->boolean('root_admin', false) ? 1 : 0;

        $data = [
            'email' => $email,
            'username' => $username,
            'name_first' => $nameFirst,
            'name_last' => $nameLast,
            'root_admin' => $rootAdmin,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        Database::update('users', $data, '`id` = :id', ['id' => $id]);
        Session::flash('success', 'User updated successfully.');
        return Response::redirect("/admin/users/view/{$id}");
    }

    public function delete(Request $request, string $id): Response
    {
        $currentUser = $request->getAttribute('user');
        if (((int)$currentUser['id']) === ((int)$id)) {
            Session::flash('error', 'You cannot delete your own account.');
            return Response::redirect('/admin/users');
        }

        $serverCount = (int)(Database::queryOne("SELECT COUNT(*) as c FROM `servers` WHERE `owner_id` = :id", ['id' => $id])['c'] ?? 0);
        if ($serverCount > 0) {
            Session::flash('error', 'Cannot delete a user who currently owns servers. Transfer or delete those servers first.');
            return Response::redirect('/admin/users');
        }

        Database::execute("DELETE FROM `subusers` WHERE `user_id` = :id", ['id' => $id]);
        Database::execute("DELETE FROM `api_keys` WHERE `user_id` = :id", ['id' => $id]);
        Database::execute("DELETE FROM `user_ssh_keys` WHERE `user_id` = :id", ['id' => $id]);
        Database::execute("DELETE FROM `users` WHERE `id` = :id", ['id' => $id]);

        Session::flash('success', 'User deleted.');
        return Response::redirect('/admin/users');
    }
}
