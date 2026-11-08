<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Remote;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;

/**
 * Ingests activity log events batched from Wings daemon.
 */
class ActivityHandler
{
    public function __invoke(Request $request): Response
    {
        $node = $request->getAttribute('node');
        $data = $request->input('data');
        if (!is_array($data)) {
            return Response::noContent();
        }

        foreach ($data as $item) {
            if (empty($item['server']) || empty($item['event'])) {
                continue;
            }

            $server = Database::queryOne(
                "SELECT `id` FROM `servers` WHERE `uuid` = :uuid LIMIT 1",
                ['uuid' => $item['server']]
            );
            if (!$server) {
                continue;
            }

            $actorId = null;
            $actorType = null;
            if (!empty($item['user'])) {
                $user = Database::queryOne(
                    "SELECT `id` FROM `users` WHERE `uuid` = :uuid LIMIT 1",
                    ['uuid' => $item['user']]
                );
                if ($user) {
                    $actorId = $user['id'];
                    $actorType = 'user';
                }
            }

            $logId = Database::insert('activity_logs', [
                'ip' => $item['ip'] ?? '127.0.0.1',
                'event' => $item['event'],
                'properties' => json_encode($item['metadata'] ?? []),
                'timestamp' => date('Y-m-d H:i:s'),
                'actor_id' => $actorId,
                'actor_type' => $actorType,
            ]);

            Database::insert('activity_log_subjects', [
                'activity_log_id' => $logId,
                'subject_id' => $server['id'],
                'subject_type' => 'server',
            ]);
        }

        return Response::noContent();
    }
}
