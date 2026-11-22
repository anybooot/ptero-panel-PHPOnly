<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Remote;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;

/**
 * Handles backup completion and restore status notifications from Wings.
 */
class BackupStatusHandler
{
    public function handleComplete(Request $request, string $backup): Response
    {
        $node = $request->getAttribute('node');
        $model = Database::queryOne(
            "SELECT * FROM `backups` WHERE `uuid` = :uuid LIMIT 1",
            ['uuid' => $backup]
        );

        if (!$model) {
            return Response::json(['error' => 'Backup not found.'], 404);
        }

        $successful = $request->boolean('successful');
        $checksum = $successful ? ($request->input('checksum_type') . ':' . $request->input('checksum')) : null;
        $bytes = $successful ? (int)$request->input('size', 0) : 0;

        Database::execute(
            "UPDATE `backups` SET
                `is_successful` = :is_successful,
                `is_locked` = :is_locked,
                `checksum` = :checksum,
                `bytes` = :bytes,
                `completed_at` = NOW()
             WHERE `id` = :id",
            [
                'is_successful' => $successful ? 1 : 0,
                'is_locked' => ($successful && !empty($model['is_locked'])) ? 1 : 0,
                'checksum' => $checksum,
                'bytes' => $bytes,
                'id' => $model['id'],
            ]
        );

        return Response::noContent();
    }

    public function handleRestore(Request $request, string $backup): Response
    {
        $model = Database::queryOne(
            "SELECT * FROM `backups` WHERE `uuid` = :uuid LIMIT 1",
            ['uuid' => $backup]
        );

        if ($model) {
            Database::execute(
                "UPDATE `servers` SET `status` = NULL WHERE `id` = :id",
                ['id' => $model['server_id']]
            );
        }

        return Response::noContent();
    }
}
