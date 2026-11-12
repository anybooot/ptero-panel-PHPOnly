<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Client\Server;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\View;
use Pterodactyl\Security\Gate;
use Pterodactyl\Wings\WingsClient;

/**
 * Handles Server Settings: SFTP credentials view, renaming, reinstalling, and Docker image configuration.
 */
class SettingsHandler
{
    public function index(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');
        $node = $server['node'];

        $egg = Database::queryOne("SELECT * FROM `eggs` WHERE `id` = :id LIMIT 1", ['id' => $server['egg_id']]);
        $dockerImages = [];
        if (!empty($egg['docker_images'])) {
            $decoded = json_decode($egg['docker_images'], true);
            if (is_array($decoded)) {
                $dockerImages = $decoded;
            }
        }

        $sftpPort = $node['daemonSFTP'] ?? 2022;
        $sftpUsername = "{$user['username']}.{$server['uuidShort']}";

        $html = View::render('server.settings', [
            'server' => $server,
            'node' => $node,
            'user' => $user,
            'egg' => $egg,
            'dockerImages' => $dockerImages,
            'sftpPort' => $sftpPort,
            'sftpUsername' => $sftpUsername,
            'activeTab' => 'settings',
        ]);

        return Response::html($html);
    }

    public function rename(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'settings.rename')) {
            return Response::json(['error' => 'You do not have permission to rename this server.'], 403);
        }

        $name = trim((string)$request->input('name', ''));
        $description = (string)$request->input('description', '');

        if ($name === '') {
            return Response::json(['error' => 'Server name cannot be empty.'], 400);
        }

        Database::execute(
            "UPDATE `servers` SET `name` = :name, `description` = :desc WHERE `id` = :id",
            ['name' => $name, 'desc' => $description, 'id' => $server['id']]
        );

        try {
            WingsClient::syncServer($server['node'], $server['uuid']);
        } catch (\Throwable) {
        }

        \Pterodactyl\Core\ActivityLogger::log('server:settings.rename', $user, ['name' => $name], $request, $server);

        return Response::noContent();
    }

    public function reinstall(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'settings.reinstall')) {
            return Response::json(['error' => 'You do not have permission to reinstall this server.'], 403);
        }

        try {
            Database::execute("UPDATE `servers` SET `status` = 'installing' WHERE `id` = :id", ['id' => $server['id']]);
            WingsClient::reinstallServer($server['node'], $server['uuid']);
            \Pterodactyl\Core\ActivityLogger::log('server:settings.reinstall', $user, [], $request, $server);
            return Response::noContent();
        } catch (\Throwable $e) {
            Database::execute("UPDATE `servers` SET `status` = NULL WHERE `id` = :id", ['id' => $server['id']]);
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function dockerImage(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'startup.docker-image')) {
            return Response::json(['error' => 'You do not have permission to update Docker images.'], 403);
        }

        $image = trim((string)$request->input('docker_image', ''));
        if ($image === '') {
            return Response::json(['error' => 'A valid image name is required.'], 400);
        }

        Database::execute("UPDATE `servers` SET `image` = :image WHERE `id` = :id", ['image' => $image, 'id' => $server['id']]);

        try {
            WingsClient::syncServer($server['node'], $server['uuid']);
        } catch (\Throwable) {
        }

        \Pterodactyl\Core\ActivityLogger::log('server:settings.rename', $user, ['name' => $name], $request, $server);

        return Response::noContent();
    }
}
