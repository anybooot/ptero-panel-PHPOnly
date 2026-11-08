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
 * Handles Server Schedules and automated tasks (cron-based commands, power, backups).
 */
class ScheduleHandler
{
    public function index(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'schedule.read')) {
            return Response::html('<h1>403 Forbidden</h1><p>You do not have permission to view schedules.</p>', 403);
        }

        $schedules = Database::query(
            "SELECT s.*, (SELECT COUNT(*) FROM `tasks` t WHERE t.`schedule_id` = s.`id`) as tasks_count
             FROM `schedules` s WHERE s.`server_id` = :server_id ORDER BY s.`id` ASC",
            ['server_id' => $server['id']]
        );

        if ($request->isJson()) {
            return Response::json(['data' => $schedules]);
        }

        $html = View::render('server.schedules', [
            'server' => $server,
            'node' => $server['node'],
            'user' => $user,
            'schedules' => $schedules,
            'activeTab' => 'schedules',
        ]);

        return Response::html($html);
    }

    public function view(Request $request, string $scheduleId): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'schedule.read')) {
            return Response::html('<h1>403 Forbidden</h1><p>You do not have permission to view schedules.</p>', 403);
        }

        $schedule = Database::queryOne(
            "SELECT * FROM `schedules` WHERE `id` = :id AND `server_id` = :server_id LIMIT 1",
            ['id' => $scheduleId, 'server_id' => $server['id']]
        );

        if (!$schedule) {
            return Response::html('<h1>404 Not Found</h1><p>Schedule not found.</p>', 404);
        }

        $tasks = Database::query(
            "SELECT * FROM `tasks` WHERE `schedule_id` = :id ORDER BY `sequence_id` ASC",
            ['id' => $schedule['id']]
        );

        if ($request->isJson()) {
            return Response::json(['schedule' => $schedule, 'tasks' => $tasks]);
        }

        $html = View::render('server.schedule_edit', [
            'server' => $server,
            'node' => $server['node'],
            'user' => $user,
            'schedule' => $schedule,
            'tasks' => $tasks,
            'activeTab' => 'schedules',
        ]);

        return Response::html($html);
    }

    public function create(Request $request): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'schedule.create')) {
            return Response::json(['error' => 'You do not have permission to create schedules.'], 403);
        }

        $name = trim((string)$request->input('name', ''));
        if ($name === '') {
            return Response::json(['error' => 'Schedule name is required.'], 400);
        }

        $id = Database::insert('schedules', [
            'server_id' => $server['id'],
            'name' => $name,
            'cron_minute' => $request->input('cron_minute', '*'),
            'cron_hour' => $request->input('cron_hour', '*'),
            'cron_day_of_month' => $request->input('cron_day_of_month', '*'),
            'cron_month' => $request->input('cron_month', '*'),
            'cron_day_of_week' => $request->input('cron_day_of_week', '*'),
            'is_active' => $request->boolean('is_active', true) ? 1 : 0,
            'only_when_online' => $request->boolean('only_when_online', false) ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return Response::json(['id' => $id, 'redirect' => "/server/{$server['uuidShort']}/schedules/{$id}"]);
    }

    public function update(Request $request, string $scheduleId): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'schedule.update')) {
            return Response::json(['error' => 'You do not have permission to update schedules.'], 403);
        }

        Database::update('schedules', [
            'name' => (string)$request->input('name', ''),
            'cron_minute' => $request->input('cron_minute', '*'),
            'cron_hour' => $request->input('cron_hour', '*'),
            'cron_day_of_month' => $request->input('cron_day_of_month', '*'),
            'cron_month' => $request->input('cron_month', '*'),
            'cron_day_of_week' => $request->input('cron_day_of_week', '*'),
            'is_active' => $request->boolean('is_active', true) ? 1 : 0,
            'only_when_online' => $request->boolean('only_when_online', false) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ], '`id` = :id AND `server_id` = :server_id', ['id' => $scheduleId, 'server_id' => $server['id']]);

        return Response::json(['success' => true]);
    }

    public function delete(Request $request, string $scheduleId): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'schedule.delete')) {
            return Response::json(['error' => 'You do not have permission to delete schedules.'], 403);
        }

        Database::execute("DELETE FROM `tasks` WHERE `schedule_id` = :id", ['id' => $scheduleId]);
        Database::execute(
            "DELETE FROM `schedules` WHERE `id` = :id AND `server_id` = :server_id",
            ['id' => $scheduleId, 'server_id' => $server['id']]
        );

        return Response::noContent();
    }

    public function executeNow(Request $request, string $scheduleId): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'schedule.update')) {
            return Response::json(['error' => 'You do not have permission to execute schedules.'], 403);
        }

        $tasks = Database::query(
            "SELECT * FROM `tasks` WHERE `schedule_id` = :id ORDER BY `sequence_id` ASC",
            ['id' => $scheduleId]
        );

        foreach ($tasks as $task) {
            try {
                if ($task['action'] === 'command') {
                    WingsClient::sendCommands($server['node'], $server['uuid'], $task['payload']);
                } elseif ($task['action'] === 'power') {
                    WingsClient::sendPowerAction($server['node'], $server['uuid'], $task['payload']);
                } elseif ($task['action'] === 'backup') {
                    $backupUuid = bin2hex(random_bytes(16));
                    WingsClient::createBackup($server['node'], $server['uuid'], $backupUuid);
                }
            } catch (\Throwable) {
                if (empty($task['continue_on_failure'])) {
                    break;
                }
            }
        }

        Database::execute(
            "UPDATE `schedules` SET `last_run_at` = NOW() WHERE `id` = :id",
            ['id' => $scheduleId]
        );

        return Response::noContent();
    }

    // Tasks Management
    public function createTask(Request $request, string $scheduleId): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'schedule.update')) {
            return Response::json(['error' => 'You do not have permission to modify schedule tasks.'], 403);
        }

        $countRow = Database::queryOne(
            "SELECT MAX(`sequence_id`) as max_seq FROM `tasks` WHERE `schedule_id` = :id",
            ['id' => $scheduleId]
        );
        $nextSeq = ((int)($countRow['max_seq'] ?? 0)) + 1;

        $id = Database::insert('tasks', [
            'schedule_id' => $scheduleId,
            'sequence_id' => $nextSeq,
            'action' => (string)$request->input('action', 'command'),
            'payload' => (string)$request->input('payload', ''),
            'time_offset' => (int)$request->input('time_offset', 0),
            'continue_on_failure' => $request->boolean('continue_on_failure', false) ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return Response::json(['id' => $id, 'success' => true]);
    }

    public function deleteTask(Request $request, string $scheduleId, string $taskId): Response
    {
        $server = $request->getAttribute('server');
        $user = $request->getAttribute('user');

        if (!Gate::allows($user, $server, 'schedule.update')) {
            return Response::json(['error' => 'You do not have permission to modify schedule tasks.'], 403);
        }

        Database::execute(
            "DELETE FROM `tasks` WHERE `id` = :task_id AND `schedule_id` = :schedule_id",
            ['task_id' => $taskId, 'schedule_id' => $scheduleId]
        );

        return Response::noContent();
    }
}
