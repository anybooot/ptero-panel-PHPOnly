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
 * Handles Server Startup command view and egg variable configuration.
 */
class StartupHandler
{
    public function index(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'startup.read')) {
            return Response::html('<h1>403 Forbidden</h1><p>You do not have permission to view startup configurations.</p>', 403);
        }

        $variables = Database::query(
            "SELECT ev.*, sv.`server_value`
             FROM `egg_variables` ev
             LEFT JOIN `server_variables` sv ON sv.`variable_id` = ev.`id` AND sv.`server_id` = :server_id
             WHERE ev.`egg_id` = :egg_id ORDER BY ev.`id` ASC",
            ['server_id' => $server['id'], 'egg_id' => $server['egg_id']]
        );

        $egg = Database::queryOne("SELECT * FROM `eggs` WHERE `id` = :id LIMIT 1", ['id' => $server['egg_id']]);

        // Build command preview
        $commandPreview = $server['startup'];
        foreach ($variables as $v) {
            $val = $v['server_value'] ?? $v['default_value'];
            $commandPreview = str_replace('{{' . $v['env_variable'] . '}}', (string)$val, $commandPreview);
        }

        if ($request->isJson()) {
            return Response::json(['invocation' => $commandPreview, 'variables' => $variables]);
        }

        $html = View::render('server.startup', [
            'server' => $server,
            'node' => $server['node'],
            'egg' => $egg,
            'user' => $user,
            'variables' => $variables,
            'commandPreview' => $commandPreview,
            'activeTab' => 'startup',
        ]);

        return Response::html($html);
    }

    public function updateVariable(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'startup.update')) {
            return Response::json(['error' => 'You do not have permission to modify startup variables.'], 403);
        }

        $key = (string)$request->input('key', '');
        $value = (string)$request->input('value', '');

        $variable = Database::queryOne(
            "SELECT * FROM `egg_variables` WHERE `env_variable` = :key AND `egg_id` = :egg_id LIMIT 1",
            ['key' => $key, 'egg_id' => $server['egg_id']]
        );

        if (!$variable) {
            return Response::json(['error' => 'The variable requested does not exist for this server.'], 404);
        }

        if (empty($variable['user_editable']) && !Gate::isRootAdmin($user)) {
            return Response::json(['error' => 'You do not have permission to edit this variable.'], 403);
        }

        // Validate rules if present
        if (!empty($variable['rules'])) {
            // Check regex
            if (preg_match('/regex:\/(.*)\/([a-z]*)/', $variable['rules'], $m)) {
                $pattern = '/' . $m[1] . '/' . $m[2];
                if (!preg_match($pattern, $value)) {
                    return Response::json(['error' => 'The value provided does not match the validation pattern.'], 400);
                }
            }
        }

        $existing = Database::queryOne(
            "SELECT `id` FROM `server_variables` WHERE `server_id` = :server_id AND `variable_id` = :variable_id LIMIT 1",
            ['server_id' => $server['id'], 'variable_id' => $variable['id']]
        );

        if ($existing) {
            Database::execute(
                "UPDATE `server_variables` SET `server_value` = :val WHERE `id` = :id",
                ['val' => $value, 'id' => $existing['id']]
            );
        } else {
            Database::insert('server_variables', [
                'server_id' => $server['id'],
                'variable_id' => $variable['id'],
                'server_value' => $value,
            ]);
        }

        try {
            WingsClient::syncServer($server['node'], $server['uuid']);
        } catch (\Throwable) {
        }

        return Response::noContent();
    }
}
