<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Admin;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;
use Pterodactyl\Core\Session;
use Pterodactyl\Core\View;

/**
 * Handles Administrator Egg management, variables, import/export.
 */
class EggHandler
{
    public function createForm(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $nests = Database::query("SELECT * FROM `nests` ORDER BY `name` ASC");

        $html = View::render('admin.eggs.new', [
            'user' => $user,
            'nests' => $nests,
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
        $description = (string)$request->input('description', '');
        $dockerImage = trim((string)$request->input('docker_image', ''));
        $startup = trim((string)$request->input('startup', ''));

        if ($nestId === 0 || $name === '' || $dockerImage === '') {
            Session::flash('error', 'Nest, Name, and Docker Image are required.');
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
            'image' => $dockerImage,
            'docker_images' => $dockerImages,
            'inherit_config_startup' => json_encode(['done' => 'Done', 'user_interaction' => [], 'strip_ansi' => false]),
            'inherit_config_stop' => 'stop',
            'inherit_config_files' => json_encode(new \stdClass()),
            'copy_script_container' => 'ghcr.io/pterodactyl/installers:alpine',
            'copy_script_entry' => 'ash',
            'copy_script_install' => '',
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

        $name = trim((string)$request->input('name', $egg['name']));
        $description = (string)$request->input('description', $egg['description']);
        $dockerImage = trim((string)$request->input('docker_image', $egg['image']));
        $dockerImagesRaw = (string)$request->input('docker_images', '');
        $startup = (string)$request->input('startup', $egg['startup']);
        $stopCommand = (string)$request->input('stop', $egg['inherit_config_stop']);
        $configFiles = (string)$request->input('config_files', $egg['inherit_config_files']);
        $installScript = (string)$request->input('install_script', $egg['copy_script_install']);
        $installContainer = (string)$request->input('install_container', $egg['copy_script_container']);
        $installEntry = (string)$request->input('install_entry', $egg['copy_script_entry']);

        $dockerImages = !empty($dockerImagesRaw) ? json_encode(array_filter(array_map('trim', explode("\n", str_replace("\r", "", $dockerImagesRaw))))) : $egg['docker_images'];

        Database::update('eggs', [
            'name' => $name,
            'description' => $description,
            'image' => $dockerImage,
            'docker_images' => $dockerImages,
            'startup' => $startup,
            'inherit_config_stop' => $stopCommand,
            'inherit_config_files' => $configFiles,
            'copy_script_install' => $installScript,
            'copy_script_container' => $installContainer,
            'copy_script_entry' => $installEntry,
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
            'features' => null,
            'docker_images' => json_decode($egg['docker_images'] ?? '[]', true),
            'images' => [$egg['image']],
            'startup' => $egg['startup'],
            'config' => [
                'files' => json_decode($egg['inherit_config_files'] ?? '{}', true),
                'startup' => json_decode($egg['inherit_config_startup'] ?? '{}', true),
                'stop' => $egg['inherit_config_stop'],
                'logs' => '{}',
                'file_denylist' => [],
            ],
            'scripts' => [
                'installation' => [
                    'script' => $egg['copy_script_install'],
                    'container' => $egg['copy_script_container'],
                    'entrypoint' => $egg['copy_script_entry'],
                ],
            ],
            'variables' => $variables,
        ];

        $json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $filename = 'egg-' . strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $egg['name'])) . '.json';

        return Response::text((string)$json, 200)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function variables(Request $request, string $id): Response
    {
        $user = $request->getAttribute('user');
        $egg = Database::queryOne("SELECT * FROM `eggs` WHERE `id` = :id LIMIT 1", ['id' => $id]);
        if (!$egg) {
            return Response::redirect('/admin/nests');
        }

        $variables = Database::query("SELECT * FROM `egg_variables` WHERE `egg_id` = :id ORDER BY `id` ASC", ['id' => $id]);

        $html = View::render('admin.eggs.variables', [
            'user' => $user,
            'egg' => $egg,
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
        $description = (string)$request->input('description', '');
        $envVariable = trim((string)$request->input('env_variable', ''));
        $defaultValue = (string)$request->input('default_value', '');
        $rules = (string)$request->input('rules', '');

        if ($name === '' || $envVariable === '') {
            Session::flash('error', 'Name and Environment Variable are required.');
            return Response::redirect("/admin/nests/egg/{$id}/variables");
        }

        Database::insert('egg_variables', [
            'egg_id' => $id,
            'name' => $name,
            'description' => $description,
            'env_variable' => $envVariable,
            'default_value' => $defaultValue,
            'user_viewable' => $request->boolean('user_viewable', true) ? 1 : 0,
            'user_editable' => $request->boolean('user_editable', true) ? 1 : 0,
            'rules' => $rules,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Session::flash('success', 'Variable added.');
        return Response::redirect("/admin/nests/egg/{$id}/variables");
    }

    public function updateVariable(Request $request, string $id, string $varId): Response
    {
        $name = trim((string)$request->input('name', ''));
        $description = (string)$request->input('description', '');
        $envVariable = trim((string)$request->input('env_variable', ''));
        $defaultValue = (string)$request->input('default_value', '');
        $rules = (string)$request->input('rules', '');

        if ($name === '' || $envVariable === '') {
            Session::flash('error', 'Name and Environment Variable are required.');
            return Response::redirect("/admin/nests/egg/{$id}/variables");
        }

        Database::update('egg_variables', [
            'name' => $name,
            'description' => $description,
            'env_variable' => $envVariable,
            'default_value' => $defaultValue,
            'user_viewable' => $request->boolean('user_viewable', true) ? 1 : 0,
            'user_editable' => $request->boolean('user_editable', true) ? 1 : 0,
            'rules' => $rules,
            'updated_at' => date('Y-m-d H:i:s'),
        ], '`id` = :id AND `egg_id` = :egg_id', ['id' => $varId, 'egg_id' => $id]);

        Session::flash('success', 'Variable updated successfully.');
        return Response::redirect("/admin/nests/egg/{$id}/variables");
    }

    public function importForm(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $nests = Database::query("SELECT * FROM `nests` ORDER BY `name` ASC");

        $html = View::render('admin.eggs.import', [
            'user' => $user,
            'nests' => $nests,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'activeAdminTab' => 'nests',
        ]);

        return Response::html($html);
    }

    public function import(Request $request): Response
    {
        $nestId = (int)$request->input('nest_id', 0);
        if ($nestId === 0) {
            Session::flash('error', 'Please select a destination nest.');
            return Response::redirect('/admin/nests/egg/import');
        }

        $jsonContent = '';
        if (isset($_FILES['egg_file']) && is_uploaded_file($_FILES['egg_file']['tmp_name'])) {
            $jsonContent = file_get_contents($_FILES['egg_file']['tmp_name']);
        } else {
            $jsonContent = trim((string)$request->input('import_json', ''));
        }

        if (empty($jsonContent)) {
            Session::flash('error', 'Please provide a valid Egg JSON file or paste the JSON content.');
            return Response::redirect('/admin/nests/egg/import');
        }

        $data = json_decode($jsonContent, true);
        if (!$data || !is_array($data) || empty($data['name'])) {
            Session::flash('error', 'The provided file is not a valid Pterodactyl Egg JSON definition.');
            return Response::redirect('/admin/nests/egg/import');
        }

        $name = trim((string)$data['name']);
        $description = (string)($data['description'] ?? '');
        $author = (string)($data['author'] ?? 'support@pterodactyl.io');
        $startup = (string)($data['startup'] ?? '');
        $dockerImages = $data['docker_images'] ?? [];
        $image = is_array($dockerImages) && !empty($dockerImages) ? array_values($dockerImages)[0] : ($data['image'] ?? 'ghcr.io/pterodactyl/yolks:java_17');

        $eggId = Database::insert('eggs', [
            'uuid' => bin2hex(random_bytes(16)),
            'nest_id' => $nestId,
            'author' => $author,
            'name' => $name,
            'description' => $description,
            'startup' => $startup,
            'image' => $image,
            'docker_images' => json_encode($dockerImages),
            'inherit_config_startup' => json_encode($data['config']['startup'] ?? []),
            'inherit_config_stop' => $data['config']['stop'] ?? 'stop',
            'inherit_config_files' => json_encode($data['config']['files'] ?? new \stdClass()),
            'copy_script_container' => $data['scripts']['installation']['container'] ?? 'ghcr.io/pterodactyl/installers:alpine',
            'copy_script_entry' => $data['scripts']['installation']['entrypoint'] ?? 'ash',
            'copy_script_install' => $data['scripts']['installation']['script'] ?? '',
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

        $nestId = (int)$request->input('nest_id', $egg['nest_id']);

        $newEggId = Database::insert('eggs', [
            'uuid' => bin2hex(random_bytes(16)),
            'nest_id' => $nestId,
            'author' => $egg['author'],
            'name' => 'Copy of ' . $egg['name'],
            'description' => $egg['description'],
            'startup' => $egg['startup'],
            'image' => $egg['image'],
            'docker_images' => $egg['docker_images'],
            'inherit_config_startup' => $egg['inherit_config_startup'],
            'inherit_config_stop' => $egg['inherit_config_stop'],
            'inherit_config_files' => $egg['inherit_config_files'],
            'copy_script_container' => $egg['copy_script_container'],
            'copy_script_entry' => $egg['copy_script_entry'],
            'copy_script_install' => $egg['copy_script_install'],
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

    public function deleteVariable(Request $request, string $id, string $varId): Response
    {
        Database::execute("DELETE FROM `server_variables` WHERE `variable_id` = :id", ['id' => $varId]);
        Database::execute("DELETE FROM `egg_variables` WHERE `id` = :id AND `egg_id` = :egg_id", ['id' => $varId, 'egg_id' => $id]);

        Session::flash('success', 'Variable deleted.');
        return Response::redirect("/admin/nests/egg/{$id}/variables");
    }

    public function delete(Request $request, string $id): Response
    {
        $serverCount = (int)(Database::queryOne("SELECT COUNT(*) as c FROM `servers` WHERE `egg_id` = :id", ['id' => $id])['c'] ?? 0);
        if ($serverCount > 0) {
            Session::flash('error', 'Cannot delete an egg that has active servers.');
            return Response::redirect('/admin/nests');
        }

        Database::execute("DELETE FROM `egg_variables` WHERE `egg_id` = :id", ['id' => $id]);
        Database::execute("DELETE FROM `eggs` WHERE `id` = :id", ['id' => $id]);

        Session::flash('success', 'Egg deleted.');
        return Response::redirect('/admin/nests');
    }
}
