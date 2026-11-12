<?php

declare(strict_types=1);

namespace Pterodactyl\Core;

class ActivityLogger
{
    public static function log(
        string $event,
        array|int|null $actor = null,
        array $properties = [],
        ?Request $request = null,
        ?array $server = null,
        ?string $description = null
    ): int {
        try {
            $actorId = is_array($actor) ? ($actor['id'] ?? null) : $actor;
            if (!$actorId && $request !== null) {
                $user = $request->getAttribute('user');
                if ($user && isset($user['id'])) {
                    $actorId = $user['id'];
                } else {
                    $actorId = Session::get('user_id');
                }
            }

            $ip = $request ? $request->getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

            $apiKey = $request ? $request->getAttribute('api_key') : null;
            $apiKeyId = $apiKey ? (int)($apiKey['id'] ?? 0) : null;
            if ($apiKeyId === 0) {
                $apiKeyId = null;
            }

            $batch = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );

            $logId = Database::insert('activity_logs', [
                'batch' => $batch,
                'event' => $event,
                'ip' => $ip,
                'description' => $description,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'api_key_id' => $apiKeyId,
                'properties' => json_encode($properties),
                'timestamp' => date('Y-m-d H:i:s'),
            ]);

            if ($server && isset($server['id'])) {
                Database::insert('activity_log_subjects', [
                    'activity_log_id' => (int)$logId,
                    'subject_type' => 'server',
                    'subject_id' => (int)$server['id'],
                ]);
            } elseif ($actorId) {
                Database::insert('activity_log_subjects', [
                    'activity_log_id' => (int)$logId,
                    'subject_type' => 'user',
                    'subject_id' => (int)$actorId,
                ]);
            }

            return (int)$logId;
        } catch (\Throwable $e) {
            error_log('ActivityLogger error: ' . $e->getMessage());
            return 0;
        }
    }

    public static function formatEvent(string $event): string
    {
        $map = [
            'auth:login' => 'Signed into account',
            'auth:logout' => 'Logged out of account',
            'auth:checkpoint' => 'Completed 2FA security checkpoint',
            'user:account.email-changed' => 'Changed account email address',
            'user:account.password-changed' => 'Updated account password',
            'user:account.two-factor.enable' => 'Enabled two-factor authentication',
            'user:account.two-factor.disable' => 'Disabled two-factor authentication',
            'user:account.api-key.create' => 'Created client API key',
            'user:account.api-key.delete' => 'Deleted client API key',
            'user:account.ssh-key.create' => 'Added public SSH key',
            'user:account.ssh-key.delete' => 'Removed public SSH key',
            'server:power.start' => 'Started server',
            'server:power.stop' => 'Stopped server',
            'server:power.restart' => 'Restarted server',
            'server:power.kill' => 'Terminated server process (Kill)',
            'server:command' => 'Executed console command',
            'server:file.write' => 'Created or edited file',
            'server:file.delete' => 'Deleted file(s)',
            'server:file.rename' => 'Renamed file or directory',
            'server:file.copy' => 'Copied file',
            'server:file.create-directory' => 'Created directory',
            'server:file.compress' => 'Archived files (ZIP/TAR)',
            'server:file.decompress' => 'Extracted archive files',
            'server:file.upload' => 'Uploaded file(s)',
            'server:database.create' => 'Created MySQL database',
            'server:database.delete' => 'Deleted MySQL database',
            'server:database.rotate-password' => 'Rotated database password',
            'server:backup.create' => 'Created server backup',
            'server:backup.delete' => 'Deleted server backup',
            'server:backup.restore' => 'Restored server backup',
            'server:backup.lock' => 'Toggled backup lock state',
            'server:subuser.create' => 'Invited new subuser to server',
            'server:subuser.update' => 'Updated subuser permissions',
            'server:subuser.delete' => 'Removed subuser access',
            'server:schedule.create' => 'Created scheduled task',
            'server:schedule.update' => 'Updated scheduled task',
            'server:schedule.delete' => 'Deleted scheduled task',
            'server:schedule.execute' => 'Manually executed schedule',
            'server:allocation.create' => 'Assigned port allocation',
            'server:allocation.primary' => 'Set primary port allocation',
            'server:allocation.delete' => 'Removed port allocation',
            'server:settings.rename' => 'Renamed server',
            'server:settings.reinstall' => 'Initiated server reinstallation',
            'server:startup.variable' => 'Modified startup variable',
        ];

        return $map[$event] ?? ucwords(str_replace([':', '.', '-'], ' ', $event));
    }
}
