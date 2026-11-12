<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Client\Server;

use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\View;
use Pterodactyl\Security\Gate;
use Pterodactyl\Security\JwtService;
use Pterodactyl\Wings\WingsClient;

/**
 * Handles File Manager browsing, viewing, editing, uploading, downloading, and archiving.
 */
class FileHandler
{
    public function index(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'file.read')) {
            if ($request->isJson() || str_starts_with($request->getPath(), '/api/')) {
                return Response::json(['errors' => [['code' => 'AccessDeniedHttpException', 'status' => '403', 'detail' => 'You do not have permission to view files.']]], 403);
            }
            return Response::html(\Pterodactyl\Core\ErrorRenderer::render(403, 'You do not have permission to view files.', 'Access Denied'), 403);
        }

        $directory = trim((string)$request->input('directory', '/'));
        $html = View::render('server.files', [
            'server' => $server,
            'node' => $server['node'],
            'user' => $user,
            'directory' => $directory,
            'activeTab' => 'files',
        ]);

        return Response::html($html);
    }

    public function listDirectory(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'file.read')) {
            return Response::json(['error' => 'You do not have permission to view files.'], 403);
        }

        $directory = (string)$request->input('directory', '/');

        try {
            $files = WingsClient::listDirectory($server['node'], $server['uuid'], $directory);
            return Response::json(['data' => $files]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function viewFile(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'file.read-content')) {
            if ($request->isJson() || str_starts_with($request->getPath(), '/api/')) {
                return Response::json(['errors' => [['code' => 'AccessDeniedHttpException', 'status' => '403', 'detail' => 'You do not have permission to read file contents.']]], 403);
            }
            return Response::html(\Pterodactyl\Core\ErrorRenderer::render(403, 'You do not have permission to read file contents.', 'Access Denied'), 403);
        }

        $file = (string)$request->input('file', '');
        $content = '';
        if ($file !== '') {
            try {
                $content = WingsClient::getFileContents($server['node'], $server['uuid'], $file);
            } catch (\Throwable $e) {
                // If new file, keep empty
            }
        }

        if ($request->isJson()) {
            return Response::json(['content' => $content]);
        }

        $html = View::render('server.file_edit', [
            'server' => $server,
            'node' => $server['node'],
            'user' => $user,
            'file' => $file,
            'content' => $content,
            'activeTab' => 'files',
        ]);

        return Response::html($html);
    }

    public function getFileContents(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'file.read-content')) {
            return Response::json(['errors' => [['code' => 'AccessDeniedHttpException', 'status' => '403', 'detail' => 'You do not have permission to read file contents.']]], 403);
        }

        $file = (string)$request->input('file', '');
        if ($file === '') {
            return Response::json(['errors' => [['code' => 'BadRequestHttpException', 'status' => '400', 'detail' => 'A file query parameter is required.']]], 400);
        }

        try {
            $content = WingsClient::getFileContents($server['node'], $server['uuid'], $file);
            return new Response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        } catch (\Throwable $e) {
            return Response::json(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'File not found on remote node.']]], 404);
        }
    }

    public function writeFile(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'file.update') && !Gate::allows($user, $server, 'file.create')) {
            return Response::json(['error' => 'You do not have permission to write files.'], 403);
        }

        $file = (string)$request->input('file', '');
        $content = $request->input('content', $request->getRawBody());

        if ($file === '') {
            return Response::json(['error' => 'No file path provided.'], 400);
        }

        try {
            WingsClient::writeFile($server['node'], $server['uuid'], $file, (string)$content);
            \Pterodactyl\Core\ActivityLogger::log('server:file.write', $user, ['file' => $file], $request, $server);
            return Response::noContent();
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function rename(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'file.update')) {
            return Response::json(['error' => 'You do not have permission to rename files.'], 403);
        }

        $root = (string)$request->input('root', '/');
        $files = (array)$request->input('files', []);

        try {
            WingsClient::renameFiles($server['node'], $server['uuid'], $root, $files);
            \Pterodactyl\Core\ActivityLogger::log('server:file.rename', $user, ['files' => $files], $request, $server);
            return Response::noContent();
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function copy(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'file.create')) {
            return Response::json(['error' => 'You do not have permission to copy files.'], 403);
        }

        $location = (string)$request->input('location', '');

        try {
            WingsClient::copyFile($server['node'], $server['uuid'], $location);
            \Pterodactyl\Core\ActivityLogger::log('server:file.copy', $user, ['location' => $location], $request, $server);
            return Response::noContent();
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'file.delete')) {
            return Response::json(['error' => 'You do not have permission to delete files.'], 403);
        }

        $root = (string)$request->input('root', '/');
        $files = (array)$request->input('files', []);

        try {
            WingsClient::deleteFiles($server['node'], $server['uuid'], $root, $files);
            \Pterodactyl\Core\ActivityLogger::log('server:file.delete', $user, ['files' => $files], $request, $server);
            return Response::noContent();
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function createFolder(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'file.create')) {
            return Response::json(['error' => 'You do not have permission to create folders.'], 403);
        }

        $path = (string)$request->input('path', '/');
        $name = trim((string)$request->input('name', ''));

        if ($name === '') {
            return Response::json(['error' => 'Folder name is required.'], 400);
        }

        try {
            WingsClient::createDirectory($server['node'], $server['uuid'], $path, $name);
            \Pterodactyl\Core\ActivityLogger::log('server:file.create-directory', $user, ['directory' => $path, 'name' => $name], $request, $server);
            return Response::noContent();
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function chmod(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'file.update')) {
            return Response::json(['error' => 'You do not have permission to change file permissions.'], 403);
        }

        $root = (string)$request->input('root', '/');
        $files = (array)$request->input('files', []);

        try {
            WingsClient::chmodFiles($server['node'], $server['uuid'], $root, $files);
            return Response::noContent();
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function compress(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'file.archive')) {
            return Response::json(['error' => 'You do not have permission to compress files.'], 403);
        }

        $root = (string)$request->input('root', '/');
        $files = (array)$request->input('files', []);

        try {
            $res = WingsClient::compressFiles($server['node'], $server['uuid'], $root, $files);
            \Pterodactyl\Core\ActivityLogger::log('server:file.compress', $user, ['files' => $files], $request, $server);
            return Response::json(['data' => $res]);
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function decompress(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'file.archive')) {
            return Response::json(['error' => 'You do not have permission to decompress archives.'], 403);
        }

        $root = (string)$request->input('root', '/');
        $file = (string)$request->input('file', '');

        try {
            WingsClient::decompressFile($server['node'], $server['uuid'], $root, $file);
            \Pterodactyl\Core\ActivityLogger::log('server:file.decompress', $user, ['file' => $file], $request, $server);
            return Response::noContent();
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function getUploadUrl(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'file.create')) {
            return Response::json(['error' => 'You do not have permission to upload files.'], 403);
        }

        $node = $server['node'];
        $jwt = new JwtService();
        $jwt->setUserUuid($user['uuid'])
            ->setScopes('file-upload')
            ->setClaims(['server_uuid' => $server['uuid']])
            ->setExpiresAt(time() + (15 * 60));

        $token = $jwt->generate(
            WingsClient::getNodeSecret($node),
            WingsClient::getNodeConnectionAddress($node),
            $user['id'] . $server['uuid']
        );

        $url = sprintf(
            '%s/upload/file?token=%s',
            WingsClient::getNodeConnectionAddress($node),
            $token
        );

        return Response::json([
            'object' => 'signed_url',
            'attributes' => ['url' => $url],
        ]);
    }

    public function getDownloadUrl(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'file.read-content')) {
            return Response::json(['error' => 'You do not have permission to download files.'], 403);
        }

        $filePath = (string)$request->input('file', '');
        $node = $server['node'];

        $jwt = new JwtService();
        $jwt->setUserUuid($user['uuid'])
            ->setScopes('file-download')
            ->setClaims([
                'file_path' => rawurldecode($filePath),
                'server_uuid' => $server['uuid'],
            ])
            ->setExpiresAt(time() + (15 * 60));

        $token = $jwt->generate(
            WingsClient::getNodeSecret($node),
            WingsClient::getNodeConnectionAddress($node),
            $user['id'] . $server['uuid']
        );

        $url = sprintf(
            '%s/download/file?token=%s',
            WingsClient::getNodeConnectionAddress($node),
            $token
        );

        return Response::json([
            'object' => 'signed_url',
            'attributes' => ['url' => $url],
        ]);
    }
}
