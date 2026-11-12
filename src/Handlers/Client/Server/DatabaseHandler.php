<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Client\Server;

use PDO;
use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\View;
use Pterodactyl\Security\Encrypter;
use Pterodactyl\Security\Gate;

/**
 * Handles Server Databases: creation, listing, password rotation, deletion.
 */
class DatabaseHandler
{
    public function index(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'database.read')) {
            if ($request->isJson() || str_starts_with($request->getPath(), '/api/')) {
                return Response::json(['errors' => [['code' => 'AccessDeniedHttpException', 'status' => '403', 'detail' => 'You do not have permission to view databases.']]], 403);
            }
            return Response::html(\Pterodactyl\Core\ErrorRenderer::render(403, 'You do not have permission to view databases.', 'Access Denied'), 403);
        }

        $databases = Database::query(
            "SELECT d.*, dh.`host`, dh.`port`, dh.`name` as host_name
             FROM `databases` d
             JOIN `database_hosts` dh ON dh.`id` = d.`database_host_id`
             WHERE d.`server_id` = :server_id",
            ['server_id' => $server['id']]
        );

        $canViewPassword = Gate::allows($user, $server, 'database.view_password');
        foreach ($databases as &$db) {
            if ($canViewPassword) {
                try {
                    $db['password_plain'] = Encrypter::decryptString($db['password']);
                } catch (\Throwable) {
                    $db['password_plain'] = $db['password'];
                }
            } else {
                $db['password_plain'] = '••••••••';
            }
        }

        if ($request->isJson()) {
            return Response::json(['data' => $databases]);
        }

        $html = View::render('server.databases', [
            'server' => $server,
            'node' => $server['node'],
            'user' => $user,
            'databases' => $databases,
            'activeTab' => 'databases',
        ]);

        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'database.create')) {
            return Response::json(['error' => 'You do not have permission to create databases.'], 403);
        }

        // Check database limit
        $limit = (int)($server['database_limit'] ?? 0);
        $countRow = Database::queryOne("SELECT COUNT(*) as count FROM `databases` WHERE `server_id` = :id", ['id' => $server['id']]);
        if ($limit > 0 && ($countRow['count'] ?? 0) >= $limit) {
            return Response::json(['error' => 'The database limit for this server has been reached.'], 400);
        }

        $databaseName = trim((string)$request->input('database', ''));
        $remote = trim((string)$request->input('remote', '%'));
        if ($databaseName === '') {
            return Response::json(['error' => 'Database name is required.'], 400);
        }

        // Get database host for node
        $dbHost = Database::queryOne(
            "SELECT * FROM `database_hosts` WHERE `node_id` = :node_id LIMIT 1",
            ['node_id' => $server['node_id']]
        );
        if (!$dbHost) {
            $dbHost = Database::queryOne("SELECT * FROM `database_hosts` LIMIT 1");
        }
        if (!$dbHost) {
            return Response::json(['error' => 'No database hosts are configured for this node.'], 400);
        }

        $prefix = "s{$server['id']}_";
        $finalDbName = $prefix . preg_replace('/[^a-zA-Z0-9_]/', '', $databaseName);
        $username = "u{$server['id']}_" . substr(bin2hex(random_bytes(4)), 0, 8);
        $plainPassword = bin2hex(random_bytes(16));

        // Connect to database host to provision database & user
        try {
            $dsn = "mysql:host={$dbHost['host']};port={$dbHost['port']}";
            $decryptedHostPassword = Encrypter::decryptString($dbHost['password']);
            $pdo = new PDO($dsn, $dbHost['username'], $decryptedHostPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$finalDbName}`");
            $pdo->exec("CREATE USER IF NOT EXISTS '{$username}'@'{$remote}' IDENTIFIED BY '{$plainPassword}'");
            $pdo->exec("GRANT ALL PRIVILEGES ON `{$finalDbName}`.* TO '{$username}'@'{$remote}'");
            $pdo->exec("FLUSH PRIVILEGES");
        } catch (\Throwable $e) {
            return Response::json(['error' => 'Failed to provision database on host: ' . $e->getMessage()], 500);
        }

        // Insert database record
        $id = Database::insert('databases', [
            'server_id' => $server['id'],
            'database_host_id' => $dbHost['id'],
            'database' => $finalDbName,
            'username' => $username,
            'remote' => $remote,
            'password' => Encrypter::encryptString($plainPassword),
            'max_connections' => (int)$request->input('max_connections', 0),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        \Pterodactyl\Core\ActivityLogger::log('server:database.create', $user, ['database' => $finalDbName], $request, $server);

        return Response::json([
            'id' => $id,
            'database' => $finalDbName,
            'username' => $username,
            'password' => $plainPassword,
            'host' => [
                'address' => $dbHost['host'],
                'port' => (int)$dbHost['port'],
            ],
        ]);
    }

    public function rotatePassword(Request $request, string $databaseId): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'database.update')) {
            return Response::json(['error' => 'You do not have permission to rotate database passwords.'], 403);
        }

        $db = Database::queryOne(
            "SELECT d.*, dh.`host`, dh.`port`, dh.`username` as host_user, dh.`password` as host_password
             FROM `databases` d
             JOIN `database_hosts` dh ON dh.`id` = d.`database_host_id`
             WHERE d.`id` = :id AND d.`server_id` = :server_id LIMIT 1",
            ['id' => $databaseId, 'server_id' => $server['id']]
        );

        if (!$db) {
            return Response::json(['error' => 'Database not found.'], 404);
        }

        $newPassword = bin2hex(random_bytes(16));

        try {
            $dsn = "mysql:host={$db['host']};port={$db['port']}";
            $decryptedHostPassword = Encrypter::decryptString($db['host_password']);
            $pdo = new PDO($dsn, $db['host_user'], $decryptedHostPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            $pdo->exec("ALTER USER '{$db['username']}'@'{$db['remote']}' IDENTIFIED BY '{$newPassword}'");
            $pdo->exec("FLUSH PRIVILEGES");
        } catch (\Throwable $e) {
            return Response::json(['error' => 'Failed to update user password on database host: ' . $e->getMessage()], 500);
        }

        Database::execute(
            "UPDATE `databases` SET `password` = :pwd, `updated_at` = NOW() WHERE `id` = :id",
            ['pwd' => Encrypter::encryptString($newPassword), 'id' => $db['id']]
        );

        \Pterodactyl\Core\ActivityLogger::log('server:database.rotate-password', $user, ['database' => $db['database']], $request, $server);

        return Response::json(['password' => $newPassword]);
    }

    public function delete(Request $request, string $databaseId): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'database.delete')) {
            return Response::json(['error' => 'You do not have permission to delete databases.'], 403);
        }

        $db = Database::queryOne(
            "SELECT d.*, dh.`host`, dh.`port`, dh.`username` as host_user, dh.`password` as host_password
             FROM `databases` d
             JOIN `database_hosts` dh ON dh.`id` = d.`database_host_id`
             WHERE d.`id` = :id AND d.`server_id` = :server_id LIMIT 1",
            ['id' => $databaseId, 'server_id' => $server['id']]
        );

        if (!$db) {
            return Response::json(['error' => 'Database not found.'], 404);
        }

        try {
            $dsn = "mysql:host={$db['host']};port={$db['port']}";
            $decryptedHostPassword = Encrypter::decryptString($db['host_password']);
            $pdo = new PDO($dsn, $db['host_user'], $decryptedHostPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            $pdo->exec("DROP DATABASE IF EXISTS `{$db['database']}`");
            $pdo->exec("DROP USER IF EXISTS '{$db['username']}'@'{$db['remote']}'");
            $pdo->exec("FLUSH PRIVILEGES");
        } catch (\Throwable $e) {
            // Log but proceed to remove record
        }

        Database::execute("DELETE FROM `databases` WHERE `id` = :id", ['id' => $db['id']]);
        \Pterodactyl\Core\ActivityLogger::log('server:database.delete', $user, ['database' => $db['database']], $request, $server);
        return Response::noContent();
    }
}
