<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Client\Server;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\View;
use Pterodactyl\Security\Gate;
use Pterodactyl\Security\JwtService;
use Pterodactyl\Wings\WingsClient;

/**
 * Handles Server Backups: creation, download, locking, restoration, and deletion.
 */
class BackupHandler
{
    public function index(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'backup.read')) {
            return Response::html('<h1>403 Forbidden</h1><p>You do not have permission to view backups.</p>', 403);
        }

        $backups = Database::query(
            "SELECT * FROM `backups` WHERE `server_id` = :server_id ORDER BY `created_at` DESC",
            ['server_id' => $server['id']]
        );

        if ($request->isJson()) {
            return Response::json(['data' => $backups]);
        }

        $html = View::render('server.backups', [
            'server' => $server,
            'node' => $server['node'],
            'user' => $user,
            'backups' => $backups,
            'activeTab' => 'backups',
        ]);

        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'backup.create')) {
            return Response::json(['error' => 'You do not have permission to create backups.'], 403);
        }

        $backupLimit = (int)($server['backup_limit'] ?? 0);
        $countRow = Database::queryOne("SELECT COUNT(*) as count FROM `backups` WHERE `server_id` = :id", ['id' => $server['id']]);
        if ($backupLimit > 0 && ($countRow['count'] ?? 0) >= $backupLimit) {
            return Response::json(['error' => 'The backup limit for this server has been reached.'], 400);
        }

        $name = trim((string)$request->input('name', ''));
        if ($name === '') {
            $name = 'Backup ' . date('Y-m-d H:i:s');
        }

        $isLocked = $request->boolean('is_locked', false) ? 1 : 0;
        $ignored = (array)$request->input('ignored', []);
        $backupUuid = bin2hex(random_bytes(16));

        $id = Database::insert('backups', [
            'server_id' => $server['id'],
            'uuid' => $backupUuid,
            'name' => $name,
            'ignored_files' => implode("\n", $ignored),
            'disk' => 'wings',
            'is_successful' => 0,
            'is_locked' => $isLocked,
            'bytes' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            WingsClient::createBackup($server['node'], $server['uuid'], $backupUuid, $ignored);
            return Response::json(['id' => $id, 'uuid' => $backupUuid, 'name' => $name]);
        } catch (\Throwable $e) {
            Database::execute("DELETE FROM `backups` WHERE `id` = :id", ['id' => $id]);
            return Response::json(['error' => 'Failed to trigger backup on Wings: ' . $e->getMessage()], 500);
        }
    }

    public function getDownloadUrl(Request $request, string $backupId): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'backup.download')) {
            return Response::json(['error' => 'You do not have permission to download backups.'], 403);
        }

        $backup = Database::queryOne(
            "SELECT * FROM `backups` WHERE (`id` = :param1 OR `uuid` = :param2) AND `server_id` = :server_id LIMIT 1",
            ['param1' => $backupId, 'param2' => $backupId, 'server_id' => $server['id']]
        );

        if (!$backup || empty($backup['is_successful'])) {
            return Response::json(['error' => 'Backup not found or has not finished generating.'], 404);
        }

        $node = $server['node'];
        $jwt = new JwtService();
        $jwt->setUserUuid($user['uuid'])
            ->setScopes('backup-download')
            ->setClaims([
                'backup_uuid' => $backup['uuid'],
                'server_uuid' => $server['uuid'],
            ])
            ->setExpiresAt(time() + (15 * 60));

        $token = $jwt->generate(
            WingsClient::getNodeSecret($node),
            WingsClient::getNodeConnectionAddress($node),
            $user['id'] . $server['uuid']
        );

        $url = sprintf(
            '%s/download/backup?token=%s',
            WingsClient::getNodeConnectionAddress($node),
            $token
        );

        return Response::json([
            'object' => 'signed_url',
            'attributes' => ['url' => $url],
        ]);
    }

    public function toggleLock(Request $request, string $backupId): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'backup.create')) {
            return Response::json(['error' => 'You do not have permission to modify backups.'], 403);
        }

        $backup = Database::queryOne(
            "SELECT * FROM `backups` WHERE (`id` = :param1 OR `uuid` = :param2) AND `server_id` = :server_id LIMIT 1",
            ['param1' => $backupId, 'param2' => $backupId, 'server_id' => $server['id']]
        );
        if (!$backup) {
            return Response::json(['error' => 'Backup not found.'], 404);
        }

        $newLock = empty($backup['is_locked']) ? 1 : 0;
        Database::execute(
            "UPDATE `backups` SET `is_locked` = :lock WHERE `id` = :id",
            ['lock' => $newLock, 'id' => $backup['id']]
        );

        return Response::json(['is_locked' => (bool)$newLock]);
    }

    public function restore(Request $request, string $backupId): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'backup.restore')) {
            return Response::json(['error' => 'You do not have permission to restore backups.'], 403);
        }

        $backup = Database::queryOne(
            "SELECT * FROM `backups` WHERE (`id` = :param1 OR `uuid` = :param2) AND `server_id` = :server_id LIMIT 1",
            ['param1' => $backupId, 'param2' => $backupId, 'server_id' => $server['id']]
        );
        if (!$backup) {
            return Response::json(['error' => 'Backup not found.'], 404);
        }

        $truncate = $request->boolean('truncate', false);

        try {
            Database::execute("UPDATE `servers` SET `status` = 'restoring_backup' WHERE `id` = :id", ['id' => $server['id']]);
            WingsClient::restoreBackup($server['node'], $server['uuid'], $backup['uuid'], null, $truncate);
            return Response::noContent();
        } catch (\Throwable $e) {
            Database::execute("UPDATE `servers` SET `status` = NULL WHERE `id` = :id", ['id' => $server['id']]);
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request, string $backupId): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'backup.delete')) {
            return Response::json(['error' => 'You do not have permission to delete backups.'], 403);
        }

        $backup = Database::queryOne(
            "SELECT * FROM `backups` WHERE (`id` = :param1 OR `uuid` = :param2) AND `server_id` = :server_id LIMIT 1",
            ['param1' => $backupId, 'param2' => $backupId, 'server_id' => $server['id']]
        );
        if (!$backup) {
            return Response::json(['error' => 'Backup not found.'], 404);
        }

        try {
            WingsClient::deleteBackup($server['node'], $server['uuid'], $backup['uuid']);
        } catch (\Throwable) {
            // Proceed to delete record even if remote fails
        }

        Database::execute("DELETE FROM `backups` WHERE `id` = :id", ['id' => $backup['id']]);
        return Response::noContent();
    }
}
