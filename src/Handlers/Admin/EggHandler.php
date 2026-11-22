<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Admin;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Core\View;

/**
 * Handles Nests & Eggs administration (create, edit, clone, export, import, variables).
 */
class EggHandler
{
    public static function normalizeEgg(array $egg): array
    {
        $images = json_decode((string)($egg['docker_images'] ?? ''), true);
        $firstImage = is_array($images) && !empty($images) ? (is_string(reset($images)) ? reset($images) : '') : 'ghcr.io/pterodactyl/yolks:java_17';

        $egg['image'] = $firstImage;
        $egg['inherit_config_stop'] = $egg['config_stop'] ?? 'stop';
        $egg['inherit_config_files'] = $egg['config_files'] ?? '{}';
        $egg['inherit_config_startup'] = $egg['config_startup'] ?? '[]';
        $egg['copy_script_container'] = $egg['script_container'] ?? 'ghcr.io/pterodactyl/installers:alpine';
        $egg['copy_script_entry'] = $egg['script_entry'] ?? 'ash';
        $egg['copy_script_install'] = $egg['script_install'] ?? '';

        return $egg;
    }

    public function createForm(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $nestId = (int)$request->input('nest_id', 0);
        $nests = Database::query("SELECT * FROM `nests` ORDER BY `name` ASC");

        $html = View::render('admin.eggs.new', [
            'user' => $user,
            'nests' => $nests,
            'selectedNest' => $nestId,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'nests',
        ]);

        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        $nestId = (int)$request->input('nest_id', 0);
        $name = trim((string)$request->input('name', ''));
        $description = trim((string)$request->input('description', ''));
        $dockerImage = trim((string)$request->input('docker_image', 'ghcr.io/pterodactyl/yolks:java_17'));
        $startup = trim((string)$request->input('startup', 'java -jar server.jar'));

        if ($nestId === 0 || $name === '') {
            Session::flash('error', 'Nest and Name are required.');
            return Response::redirect('/admin/nests/egg/new');
        }

        $uuid = bin2hex(random_bytes(16));
        $dockerImages = json_encode([$dockerImage => $dockerImage]);

        $eggId = Database::insert('eggs', [
            'uuid' => $uuid,
            'nest_id' => $nestId,
            'author' => 'support@pterodactyl.io',
            'name' => $name,
            'description' => $description,
            'startup' => $startup,
            'docker_images' => $dockerImages,
            'config_startup' => json_encode(['done' => 'Done', 'user_interaction' => [], 'strip_ansi' => false]),
            'config_stop' => 'stop',
            'config_files' => json_encode(new \stdClass()),
            'script_container' => 'ghcr.io/pterodactyl/installers:alpine',
            'script_entry' => 'ash',
            'script_install' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Session::flash('success', 'Egg created successfully.');
        return Response::redirect("/admin/nests/egg/{$eggId}");
    }

    public function view(Request $request, string $id): Response
    {
        $user = $request->getAttribute('user');
        $egg = Database::queryOne("SELECT * FROM `eggs` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$egg) {
            return Response::redirect('/admin/nests');
        }

        $egg = self::normalizeEgg($egg);
        $nest = Database::queryOne("SELECT * FROM `nests` WHERE `id` = :id LIMIT 1", ['id' => $egg['nest_id']]);
        $variables = Database::query("SELECT * FROM `egg_variables` WHERE `egg_id` = :id ORDER BY `id` ASC", ['id' => $id]);

        $html = View::render('admin.eggs.view', [
            'user' => $user,
            'egg' => $egg,
            'nest' => $nest,
            'variables' => $variables,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'nests',
            'activeEggTab' => 'details',
        ]);

        return Response::html($html);
    }

    public function update(Request $request, string $id): Response
    {
        $egg = Database::queryOne("SELECT * FROM `eggs` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$egg) {
            return Response::redirect('/admin/nests');
        }

        $egg = self::normalizeEgg($egg);

        $name = trim((string)$request->input('name', $egg['name']));
        $description = (string)$request->input('description', $egg['description']);
        $dockerImage = trim((string)$request->input('docker_image', $egg['image']));
        $dockerImagesRaw = (string)$request->input('docker_images', '');
        $startup = (string)$request->input('startup', $egg['startup']);
        $stopCommand = (string)$request->input('stop', $egg['config_stop']);
        $configFiles = (string)$request->input('config_files', $egg['config_files']);
        $installScript = (string)$request->input('install_script', $egg['script_install']);
        $installContainer = (string)$request->input('install_container', $egg['script_container']);
        $installEntry = (string)$request->input('install_entry', $egg['script_entry']);

        $dockerImages = !empty($dockerImagesRaw) ? json_encode(array_filter(array_map('trim', explode("\n", str_replace("\r", "", $dockerImagesRaw))))) : $egg['docker_images'];

        Database::update('eggs', [
            'name' => $name,
            'description' => $description,
            'docker_images' => $dockerImages,
            'startup' => $startup,
            'config_stop' => $stopCommand,
            'config_files' => $configFiles,
            'script_install' => $installScript,
            'script_container' => $installContainer,
            'script_entry' => $installEntry,
            'force_outgoing_ip' => $request->boolean('force_outgoing_ip', false) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ], '`id` = :id', ['id' => $id]);

        Session::flash('success', 'Egg updated successfully.');
        return Response::redirect("/admin/nests/egg/{$id}");
    }

    public function export(Request $request, string $id): Response
    {
        $egg = Database::queryOne("SELECT * FROM `eggs` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$egg) {
            return Response::redirect('/admin/nests');
        }

        $egg = self::normalizeEgg($egg);
        $variables = Database::query("SELECT * FROM `egg_variables` WHERE `egg_id` = :id", ['id' => $id]);

        $exportData = [
            '_comment' => 'DO NOT EDIT THIS FILE. IT WAS EXPORTED FROM PTERODACTYL PANEL.',
            'meta' => [
                'version' => 'PTDL_v2',
                'update_url' => null,
            ],
            'exported_at' => date('c'),
            'name' => $egg['name'],
            'author' => $egg['author'],
            'description' => $egg['description'],
            'features' => json_decode($egg['features'] ?? '[]', true),
            'docker_images' => json_decode($egg['docker_images'] ?? '[]', true),
            'startup' => $egg['startup'],
            'config' => [
                'files' => json_decode($egg['config_files'] ?? '{}', true),
                'startup' => json_decode($egg['config_startup'] ?? '{}', true),
                'stop' => $egg['config_stop'],
                'logs' => '{}',
                'file_denylist' => [],
            ],
            'scripts' => [
                'installation' => [
                    'script' => $egg['script_install'],
                    'container' => $egg['script_container'],
                    'entrypoint' => $egg['script_entry'],
                ],
            ],
            'variables' => $variables,
        ];

        $json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $filename = 'egg-' . strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $egg['name'])) . '.json';

        return Response::text((string)$json, 200)
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->header('Content-Type', 'application/json');
    }

    public function variables(Request $request, string $id): Response
    {
        $user = $request->getAttribute('user');
        $egg = Database::queryOne("SELECT * FROM `eggs` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$egg) {
            return Response::redirect('/admin/nests');
        }

        $egg = self::normalizeEgg($egg);
        $nest = Database::queryOne("SELECT * FROM `nests` WHERE `id` = :id LIMIT 1", ['id' => $egg['nest_id']]);
        $variables = Database::query("SELECT * FROM `egg_variables` WHERE `egg_id` = :id ORDER BY `id` ASC", ['id' => $id]);

        $html = View::render('admin.eggs.variables', [
            'user' => $user,
            'egg' => $egg,
            'nest' => $nest,
            'variables' => $variables,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'nests',
            'activeEggTab' => 'variables',
        ]);

        return Response::html($html);
    }

    public function createVariable(Request $request, string $id): Response
    {
        $name = trim((string)$request->input('name', ''));
        $description = trim((string)$request->input('description', ''));
        $envVariable = trim((string)$request->input('env_variable', ''));
        $defaultValue = (string)$request->input('default_value', '');
        $rules = trim((string)$request->input('rules', 'required|string'));

        if ($name === '' || $envVariable === '') {
            Session::flash('error', 'Name and Environment Variable name are required.');
            return Response::redirect("/admin/nests/egg/{$id}/variables");
        }

        Database::insert('egg_variables', [
            'egg_id' => (int)$id,
            'name' => $name,
            'description' => $description,
            'env_variable' => strtoupper($envVariable),
            'default_value' => $defaultValue,
            'user_viewable' => $request->boolean('user_viewable', true) ? 1 : 0,
            'user_editable' => $request->boolean('user_editable', true) ? 1 : 0,
            'rules' => $rules,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Session::flash('success', 'Variable created successfully.');
        return Response::redirect("/admin/nests/egg/{$id}/variables");
    }

    public function updateVariable(Request $request, string $id, string $varId): Response
    {
        $name = trim((string)$request->input('name', ''));
        $description = trim((string)$request->input('description', ''));
        $envVariable = trim((string)$request->input('env_variable', ''));
        $defaultValue = (string)$request->input('default_value', '');
        $rules = trim((string)$request->input('rules', 'required|string'));

        if ($name === '' || $envVariable === '') {
            Session::flash('error', 'Name and Environment Variable name are required.');
            return Response::redirect("/admin/nests/egg/{$id}/variables");
        }

        Database::update('egg_variables', [
            'name' => $name,
            'description' => $description,
            'env_variable' => strtoupper($envVariable),
            'default_value' => $defaultValue,
            'user_viewable' => $request->boolean('user_viewable', true) ? 1 : 0,
            'user_editable' => $request->boolean('user_editable', true) ? 1 : 0,
            'rules' => $rules,
            'updated_at' => date('Y-m-d H:i:s'),
        ], '`id` = :var_id AND `egg_id` = :egg_id', ['var_id' => $varId, 'egg_id' => $id]);

        Session::flash('success', 'Variable updated successfully.');
        return Response::redirect("/admin/nests/egg/{$id}/variables");
    }

    public function deleteVariable(Request $request, string $id, string $varId): Response
    {
        Database::execute(
            "DELETE FROM `egg_variables` WHERE `id` = :var_id AND `egg_id` = :egg_id",
            ['var_id' => $varId, 'egg_id' => $id]
        );

        Session::flash('success', 'Variable deleted.');
        return Response::redirect("/admin/nests/egg/{$id}/variables");
    }

    public function importForm(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $nestId = (int)$request->input('nest_id', 0);
        $nests = Database::query("SELECT * FROM `nests` ORDER BY `name` ASC");

        $html = View::render('admin.eggs.import', [
            'user' => $user,
            'nests' => $nests,
            'selectedNest' => $nestId,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'nests',
        ]);

        return Response::html($html);
    }

    public function import(Request $request): Response
    {
        $nestId = (int)$request->input('nest_id', 0);
        $jsonInput = (string)$request->input('json_content', '');

        if ($nestId === 0) {
            Session::flash('error', 'Please select a Nest.');
            return Response::redirect('/admin/nests/egg/import');
        }

        // Check if file was uploaded
        if (isset($_FILES['egg_file']) && $_FILES['egg_file']['error'] === UPLOAD_ERR_OK) {
            $jsonInput = (string)file_get_contents($_FILES['egg_file']['tmp_name']);
        }

        $data = json_decode($jsonInput, true);
        if (!is_array($data) || empty($data['name'])) {
            Session::flash('error', 'Invalid egg JSON format.');
            return Response::redirect('/admin/nests/egg/import');
        }

        $name = (string)$data['name'];
        $description = (string)($data['description'] ?? '');
        $author = (string)($data['author'] ?? 'support@pterodactyl.io');
        $startup = (string)($data['startup'] ?? '');
        $dockerImages = $data['docker_images'] ?? [];

        $eggId = Database::insert('eggs', [
            'uuid' => bin2hex(random_bytes(16)),
            'nest_id' => $nestId,
            'author' => $author,
            'name' => $name,
            'description' => $description,
            'startup' => $startup,
            'docker_images' => json_encode($dockerImages),
            'config_startup' => json_encode($data['config']['startup'] ?? []),
            'config_stop' => $data['config']['stop'] ?? 'stop',
            'config_files' => json_encode($data['config']['files'] ?? new \stdClass()),
            'script_container' => $data['scripts']['installation']['container'] ?? 'ghcr.io/pterodactyl/installers:alpine',
            'script_entry' => $data['scripts']['installation']['entrypoint'] ?? 'ash',
            'script_install' => $data['scripts']['installation']['script'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (!empty($data['variables']) && is_array($data['variables'])) {
            foreach ($data['variables'] as $var) {
                Database::insert('egg_variables', [
                    'egg_id' => $eggId,
                    'name' => $var['name'] ?? '',
                    'description' => $var['description'] ?? '',
                    'env_variable' => $var['env_variable'] ?? '',
                    'default_value' => (string)($var['default_value'] ?? ''),
                    'user_viewable' => !empty($var['user_viewable']) ? 1 : 0,
                    'user_editable' => !empty($var['user_editable']) ? 1 : 0,
                    'rules' => $var['rules'] ?? 'required|string',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        Session::flash('success', "Egg [{$name}] imported successfully.");
        return Response::redirect("/admin/nests/egg/{$eggId}");
    }

    public function cloneEgg(Request $request, string $id): Response
    {
        $egg = Database::queryOne("SELECT * FROM `eggs` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$egg) {
            return Response::redirect('/admin/nests');
        }

        $egg = self::normalizeEgg($egg);
        $nestId = (int)$request->input('nest_id', $egg['nest_id']);

        $newEggId = Database::insert('eggs', [
            'uuid' => bin2hex(random_bytes(16)),
            'nest_id' => $nestId,
            'author' => $egg['author'],
            'name' => 'Copy of ' . $egg['name'],
            'description' => $egg['description'],
            'startup' => $egg['startup'],
            'docker_images' => $egg['docker_images'],
            'config_startup' => $egg['config_startup'],
            'config_stop' => $egg['config_stop'],
            'config_files' => $egg['config_files'],
            'script_container' => $egg['script_container'],
            'script_entry' => $egg['script_entry'],
            'script_install' => $egg['script_install'],
            'force_outgoing_ip' => $egg['force_outgoing_ip'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $vars = Database::query("SELECT * FROM `egg_variables` WHERE `egg_id` = :id", ['id' => $id]);
        foreach ($vars as $var) {
            Database::insert('egg_variables', [
                'egg_id' => $newEggId,
                'name' => $var['name'],
                'description' => $var['description'],
                'env_variable' => $var['env_variable'],
                'default_value' => $var['default_value'],
                'user_viewable' => $var['user_viewable'],
                'user_editable' => $var['user_editable'],
                'rules' => $var['rules'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        Session::flash('success', "Egg cloned successfully as [Copy of {$egg['name']}].");
        return Response::redirect("/admin/nests/egg/{$newEggId}");
    }

    public function delete(Request $request, string $id): Response
    {
        $serverCount = Database::queryOne(
            "SELECT COUNT(*) as count FROM `servers` WHERE `egg_id` = :id",
            ['id' => $id]
        );

        if (($serverCount['count'] ?? 0) > 0) {
            Session::flash('error', 'Cannot delete an egg that has active servers attached to it.');
            return Response::redirect("/admin/nests/egg/{$id}");
        }

        Database::execute("DELETE FROM `egg_variables` WHERE `egg_id` = :id", ['id' => $id]);
        Database::execute("DELETE FROM `eggs` WHERE `id` = :id", ['id' => $id]);

        Session::flash('success', 'Egg deleted successfully.');
        return Response::redirect('/admin/nests');
    }
}
