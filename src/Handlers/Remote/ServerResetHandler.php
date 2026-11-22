<?php

declare(strict_types=1);

namespace Pterodactyl\Handlers\Remote;

use Pterodactyl\Core\Database;
use Pterodactyl\Core\Request;
use Pterodactyl\Core\Response;

/**
 * Resets installing/restoring states on node reboot.
 */
class ServerResetHandler
{
    public function __invoke(Request $request): Response
    {
        $node = $request->getAttribute('node');
        if (!$node) {
            return Response::json(['error' => 'Node not identified.'], 403);
        }

        Database::execute(
            "UPDATE `servers` SET `status` = NULL WHERE `node_id` = :node_id AND `status` IN ('installing', 'restoring_backup')",
            ['node_id' => $node['id']]
        );

        return Response::noContent();
    }
}
