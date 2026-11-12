<?php

declare(strict_types=1);

namespace Pterodactyl;

use Pterodactyl\Core\Router;
use Pterodactyl\Middleware\AdminMiddleware;
use Pterodactyl\Middleware\AuthMiddleware;
use Pterodactyl\Middleware\CsrfMiddleware;
use Pterodactyl\Middleware\DaemonAuthMiddleware;
use Pterodactyl\Middleware\ServerAccessMiddleware;
use Pterodactyl\Handlers\Auth;
use Pterodactyl\Handlers\Client;
use Pterodactyl\Handlers\Client\Server;
use Pterodactyl\Handlers\Remote;
use Pterodactyl\Handlers\Admin;
use Pterodactyl\Handlers\ApplicationApi;
use Pterodactyl\Handlers\ClientApi;

class Routes
{
    public static function register(Router $router): void
    {
        // ================= AUTHENTICATION =================
        $router->get('/auth/login', [Auth\LoginHandler::class, 'showLogin']);
        $router->post('/auth/login', [Auth\LoginHandler::class, 'login'], [CsrfMiddleware::class]);
        $router->post('/auth/logout', [Auth\LoginHandler::class, 'logout']);
        $router->get('/auth/checkpoint', [Auth\TwoFactorHandler::class, 'showCheckpoint']);
        $router->post('/auth/checkpoint', [Auth\TwoFactorHandler::class, 'verifyCheckpoint'], [CsrfMiddleware::class]);

        // ================= REMOTE API (Wings Daemon) =================
        $router->group(['prefix' => '/api/remote', 'middleware' => [DaemonAuthMiddleware::class]], function (Router $r) {
            $r->post('/sftp/auth', Remote\SftpAuthHandler::class);
            $r->get('/servers', Remote\ServerListHandler::class);
            $r->post('/servers/reset', Remote\ServerResetHandler::class);
            $r->post('/activity', Remote\ActivityHandler::class);

            $r->get('/servers/{uuid}', Remote\ServerDetailsHandler::class);
            $r->get('/servers/{uuid}/install', [Remote\ServerInstallHandler::class, 'getInstallData']);
            $r->post('/servers/{uuid}/install', [Remote\ServerInstallHandler::class, 'handleInstallComplete']);

            $r->post('/backups/{backup}', [Remote\BackupStatusHandler::class, 'handleComplete']);
            $r->post('/backups/{backup}/restore', [Remote\BackupStatusHandler::class, 'handleRestore']);
        });

        // ================= APPLICATION API =================
        $router->group(['prefix' => '/api/application'], function (Router $r) {
            // Users
            $r->get('/users', [ApplicationApi\ApplicationApiController::class, 'listUsers']);
            $r->post('/users', [ApplicationApi\ApplicationApiController::class, 'createUser']);
            $r->get('/users/{id}', [ApplicationApi\ApplicationApiController::class, 'viewUser']);
            $r->patch('/users/{id}', [ApplicationApi\ApplicationApiController::class, 'updateUser']);
            $r->delete('/users/{id}', [ApplicationApi\ApplicationApiController::class, 'deleteUser']);
            $r->get('/users/external/{externalId}', [ApplicationApi\ApplicationApiController::class, 'viewUserExternal']);

            // Nodes
            $r->get('/nodes', [ApplicationApi\ApplicationApiController::class, 'listNodes']);
            $r->post('/nodes', [ApplicationApi\ApplicationApiController::class, 'createNode']);
            $r->get('/nodes/{id}', [ApplicationApi\ApplicationApiController::class, 'viewNode']);
            $r->get('/nodes/{id}/configuration', [ApplicationApi\ApplicationApiController::class, 'viewNodeConfiguration']);
            $r->delete('/nodes/{id}', [ApplicationApi\ApplicationApiController::class, 'deleteNode']);
            $r->get('/nodes/{id}/allocations', [ApplicationApi\ApplicationApiController::class, 'listNodeAllocations']);
            $r->post('/nodes/{id}/allocations', [ApplicationApi\ApplicationApiController::class, 'createNodeAllocations']);
            $r->delete('/nodes/{id}/allocations/{allocationId}', [ApplicationApi\ApplicationApiController::class, 'deleteNodeAllocation']);

            // Locations
            $r->get('/locations', [ApplicationApi\ApplicationApiController::class, 'listLocations']);
            $r->post('/locations', [ApplicationApi\ApplicationApiController::class, 'createLocation']);
            $r->get('/locations/{id}', [ApplicationApi\ApplicationApiController::class, 'viewLocation']);
            $r->delete('/locations/{id}', [ApplicationApi\ApplicationApiController::class, 'deleteLocation']);

            // Servers
            $r->get('/servers', [ApplicationApi\ApplicationApiController::class, 'listServers']);
            $r->post('/servers', [ApplicationApi\ApplicationApiController::class, 'createServer']);
            $r->get('/servers/{id}', [ApplicationApi\ApplicationApiController::class, 'viewServer']);
            $r->get('/servers/external/{externalId}', [ApplicationApi\ApplicationApiController::class, 'viewServerExternal']);
            $r->patch('/servers/{id}/details', [ApplicationApi\ApplicationApiController::class, 'updateServerDetails']);
            $r->patch('/servers/{id}/build', [ApplicationApi\ApplicationApiController::class, 'updateServerBuild']);
            $r->patch('/servers/{id}/startup', [ApplicationApi\ApplicationApiController::class, 'updateServerStartup']);
            $r->post('/servers/{id}/suspend', [ApplicationApi\ApplicationApiController::class, 'suspendServer']);
            $r->post('/servers/{id}/unsuspend', [ApplicationApi\ApplicationApiController::class, 'unsuspendServer']);
            $r->post('/servers/{id}/reinstall', [ApplicationApi\ApplicationApiController::class, 'reinstallServer']);
            $r->delete('/servers/{id}', [ApplicationApi\ApplicationApiController::class, 'deleteServer']);
            $r->delete('/servers/{id}/force', [ApplicationApi\ApplicationApiController::class, 'deleteServer']);
            $r->get('/servers/{id}/databases', [ApplicationApi\ApplicationApiController::class, 'listServerDatabases']);
            $r->post('/servers/{id}/databases', [ApplicationApi\ApplicationApiController::class, 'createServerDatabase']);
            $r->post('/servers/{id}/databases/{databaseId}/reset-password', [ApplicationApi\ApplicationApiController::class, 'resetServerDatabasePassword']);
            $r->delete('/servers/{id}/databases/{databaseId}', [ApplicationApi\ApplicationApiController::class, 'deleteServerDatabase']);

            // Nests & Eggs
            $r->get('/nests', [ApplicationApi\ApplicationApiController::class, 'listNests']);
            $r->get('/nests/{id}', [ApplicationApi\ApplicationApiController::class, 'viewNest']);
            $r->get('/nests/{id}/eggs', [ApplicationApi\ApplicationApiController::class, 'listNestEggs']);
            $r->get('/nests/{nestId}/eggs/{eggId}', [ApplicationApi\ApplicationApiController::class, 'viewNestEgg']);

            // Database Hosts
            $r->get('/database-hosts', [ApplicationApi\ApplicationApiController::class, 'listDatabaseHosts']);
            $r->post('/database-hosts', [ApplicationApi\ApplicationApiController::class, 'createDatabaseHost']);
            $r->get('/database-hosts/{id}', [ApplicationApi\ApplicationApiController::class, 'viewDatabaseHost']);
            $r->delete('/database-hosts/{id}', [ApplicationApi\ApplicationApiController::class, 'deleteDatabaseHost']);
        });

        // ================= CLIENT DASHBOARD & ACCOUNT (Auth required) =================
        $router->group(['middleware' => [AuthMiddleware::class]], function (Router $r) {
            $r->get('/', [Client\DashboardHandler::class, 'index']);

            // Client Server Listing API
            $r->get('/api/client', [ClientApi\ClientApiController::class, 'listServers']);
            $r->get('/api/client/permissions', [ClientApi\ClientApiController::class, 'getPermissions']);

            // Client Account API & Web Views
            $r->get('/account', [Client\AccountHandler::class, 'overview']);
            $r->get('/api/client/account', [ClientApi\ClientApiController::class, 'getAccount']);
            $r->post('/account/email', [Client\AccountHandler::class, 'updateEmail'], [CsrfMiddleware::class]);
            $r->put('/api/client/account/email', [Client\AccountHandler::class, 'updateEmail']);
            $r->post('/account/password', [Client\AccountHandler::class, 'updatePassword'], [CsrfMiddleware::class]);
            $r->put('/api/client/account/password', [Client\AccountHandler::class, 'updatePassword']);
            
            $r->get('/account/two-factor', [Client\AccountHandler::class, 'getTwoFactor']);
            $r->get('/api/client/account/two-factor', [Client\AccountHandler::class, 'getTwoFactor']);
            $r->post('/account/two-factor', [Client\AccountHandler::class, 'enableTwoFactor']);
            $r->post('/api/client/account/two-factor', [Client\AccountHandler::class, 'enableTwoFactor']);
            $r->post('/account/two-factor/disable', [Client\AccountHandler::class, 'disableTwoFactor']);
            $r->post('/api/client/account/two-factor/disable', [Client\AccountHandler::class, 'disableTwoFactor']);

            $r->get('/account/api', [Client\AccountHandler::class, 'apiKeys']);
            $r->get('/api/client/account/api-keys', [Client\AccountHandler::class, 'apiKeys']);
            $r->post('/account/api', [Client\AccountHandler::class, 'createApiKey'], [CsrfMiddleware::class]);
            $r->post('/api/client/account/api-keys', [Client\AccountHandler::class, 'createApiKey']);
            $r->delete('/account/api/{identifier}', [Client\AccountHandler::class, 'deleteApiKey']);
            $r->delete('/api/client/account/api-keys/{identifier}', [Client\AccountHandler::class, 'deleteApiKey']);

            $r->get('/account/ssh', [Client\AccountHandler::class, 'sshKeys']);
            $r->get('/api/client/account/ssh-keys', [Client\AccountHandler::class, 'sshKeys']);
            $r->post('/account/ssh', [Client\AccountHandler::class, 'createSshKey'], [CsrfMiddleware::class]);
            $r->post('/api/client/account/ssh-keys', [Client\AccountHandler::class, 'createSshKey']);
            $r->post('/account/ssh/delete', [Client\AccountHandler::class, 'deleteSshKey']);
            $r->delete('/account/ssh/{fingerprint}', [Client\AccountHandler::class, 'deleteSshKey']);
            $r->delete('/api/client/account/ssh-keys/{fingerprint}', [Client\AccountHandler::class, 'deleteSshKey']);

            $r->get('/account/activity', [Client\AccountHandler::class, 'activity']);
            $r->get('/api/client/account/activity', [Client\AccountHandler::class, 'activity']);
        });

        // ================= CLIENT SERVER CONTROL =================
        $router->group(['middleware' => [AuthMiddleware::class, ServerAccessMiddleware::class]], function (Router $r) {
            // Server View & Details API
            $r->get('/server/{server}', [Server\ConsoleHandler::class, 'index']);
            $r->get('/api/client/servers/{server}', [ClientApi\ClientApiController::class, 'viewServer']);
            $r->get('/api/client/servers/{server}/resources', [ClientApi\ClientApiController::class, 'getResources']);
            $r->get('/api/client/servers/{server}/activity', [ClientApi\ClientApiController::class, 'getServerActivity']);

            // Console, WebSocket & Power
            $r->get('/api/client/servers/{server}/websocket', [Server\ConsoleHandler::class, 'getWebsocketToken']);
            $r->post('/api/client/servers/{server}/power', [Server\PowerHandler::class, 'sendPower']);
            $r->post('/api/client/servers/{server}/command', [Server\CommandHandler::class, 'sendCommand']);

            // Files
            $r->get('/server/{server}/files', [Server\FileHandler::class, 'index']);
            $r->get('/api/client/servers/{server}/files/list', [Server\FileHandler::class, 'listDirectory']);
            $r->get('/server/{server}/files/edit', [Server\FileHandler::class, 'viewFile']);
            $r->get('/api/client/servers/{server}/files/contents', [Server\FileHandler::class, 'getFileContents']);
            $r->post('/api/client/servers/{server}/files/write', [Server\FileHandler::class, 'writeFile']);
            $r->put('/api/client/servers/{server}/files/rename', [Server\FileHandler::class, 'rename']);
            $r->post('/api/client/servers/{server}/files/copy', [Server\FileHandler::class, 'copy']);
            $r->post('/api/client/servers/{server}/files/delete', [Server\FileHandler::class, 'delete']);
            $r->post('/api/client/servers/{server}/files/create-folder', [Server\FileHandler::class, 'createFolder']);
            $r->post('/api/client/servers/{server}/files/chmod', [Server\FileHandler::class, 'chmod']);
            $r->post('/api/client/servers/{server}/files/compress', [Server\FileHandler::class, 'compress']);
            $r->post('/api/client/servers/{server}/files/decompress', [Server\FileHandler::class, 'decompress']);
            $r->get('/api/client/servers/{server}/files/upload', [Server\FileHandler::class, 'getUploadUrl']);
            $r->get('/api/client/servers/{server}/files/download', [Server\FileHandler::class, 'getDownloadUrl']);

            // Databases
            $r->get('/server/{server}/databases', [Server\DatabaseHandler::class, 'index']);
            $r->get('/api/client/servers/{server}/databases', [Server\DatabaseHandler::class, 'index']);
            $r->post('/api/client/servers/{server}/databases', [Server\DatabaseHandler::class, 'create']);
            $r->post('/api/client/servers/{server}/databases/{id}/rotate-password', [Server\DatabaseHandler::class, 'rotatePassword']);
            $r->delete('/api/client/servers/{server}/databases/{id}', [Server\DatabaseHandler::class, 'delete']);

            // Schedules
            $r->get('/server/{server}/schedules', [Server\ScheduleHandler::class, 'index']);
            $r->get('/api/client/servers/{server}/schedules', [Server\ScheduleHandler::class, 'index']);
            $r->post('/api/client/servers/{server}/schedules', [Server\ScheduleHandler::class, 'create']);
            $r->get('/server/{server}/schedules/{id}', [Server\ScheduleHandler::class, 'view']);
            $r->get('/api/client/servers/{server}/schedules/{id}', [Server\ScheduleHandler::class, 'view']);
            $r->post('/api/client/servers/{server}/schedules/{id}', [Server\ScheduleHandler::class, 'update']);
            $r->post('/api/client/servers/{server}/schedules/{id}/execute', [Server\ScheduleHandler::class, 'executeNow']);
            $r->delete('/api/client/servers/{server}/schedules/{id}', [Server\ScheduleHandler::class, 'delete']);
            $r->post('/api/client/servers/{server}/schedules/{id}/tasks', [Server\ScheduleHandler::class, 'createTask']);
            $r->delete('/api/client/servers/{server}/schedules/{id}/tasks/{taskId}', [Server\ScheduleHandler::class, 'deleteTask']);

            // Users / Subusers
            $r->get('/server/{server}/users', [Server\SubuserHandler::class, 'index']);
            $r->get('/api/client/servers/{server}/users', [Server\SubuserHandler::class, 'index']);
            $r->post('/api/client/servers/{server}/users', [Server\SubuserHandler::class, 'create']);
            $r->post('/api/client/servers/{server}/users/{id}', [Server\SubuserHandler::class, 'update']);
            $r->delete('/api/client/servers/{server}/users/{id}', [Server\SubuserHandler::class, 'delete']);

            // Backups
            $r->get('/server/{server}/backups', [Server\BackupHandler::class, 'index']);
            $r->get('/api/client/servers/{server}/backups', [Server\BackupHandler::class, 'index']);
            $r->post('/api/client/servers/{server}/backups', [Server\BackupHandler::class, 'create']);
            $r->get('/api/client/servers/{server}/backups/{id}/download', [Server\BackupHandler::class, 'getDownloadUrl']);
            $r->post('/api/client/servers/{server}/backups/{id}/lock', [Server\BackupHandler::class, 'toggleLock']);
            $r->post('/api/client/servers/{server}/backups/{id}/restore', [Server\BackupHandler::class, 'restore']);
            $r->delete('/api/client/servers/{server}/backups/{id}', [Server\BackupHandler::class, 'delete']);

            // Network Allocations
            $r->get('/server/{server}/network', [Server\NetworkHandler::class, 'index']);
            $r->get('/api/client/servers/{server}/network/allocations', [Server\NetworkHandler::class, 'index']);
            $r->post('/api/client/servers/{server}/network/allocations', [Server\NetworkHandler::class, 'assign']);
            $r->post('/api/client/servers/{server}/network/allocations/{id}/primary', [Server\NetworkHandler::class, 'setPrimary']);
            $r->post('/api/client/servers/{server}/network/allocations/{id}/notes', [Server\NetworkHandler::class, 'setNotes']);
            $r->delete('/api/client/servers/{server}/network/allocations/{id}', [Server\NetworkHandler::class, 'delete']);

            // Startup
            $r->get('/server/{server}/startup', [Server\StartupHandler::class, 'index']);
            $r->get('/api/client/servers/{server}/startup', [Server\StartupHandler::class, 'index']);
            $r->put('/api/client/servers/{server}/startup/variable', [Server\StartupHandler::class, 'updateVariable']);

            // Settings
            $r->get('/server/{server}/settings', [Server\SettingsHandler::class, 'index']);
            $r->get('/api/client/servers/{server}/settings', [Server\SettingsHandler::class, 'index']);
            $r->post('/api/client/servers/{server}/settings/rename', [Server\SettingsHandler::class, 'rename']);
            $r->post('/api/client/servers/{server}/settings/reinstall', [Server\SettingsHandler::class, 'reinstall']);
            $r->put('/api/client/servers/{server}/settings/docker-image', [Server\SettingsHandler::class, 'dockerImage']);

            // Activity Log
            $r->get('/server/{server}/activity', [Server\ActivityHandler::class, 'index']);
        });

        // ================= ADMINISTRATOR PANEL (root_admin required) =================
        $router->group(['prefix' => '/admin', 'middleware' => [AuthMiddleware::class, AdminMiddleware::class]], function (Router $r) {
            $r->get('/', [Admin\OverviewHandler::class, 'index']);

            // Locations
            $r->get('/locations', [Admin\LocationHandler::class, 'index']);
            $r->post('/locations', [Admin\LocationHandler::class, 'create'], [CsrfMiddleware::class]);
            $r->get('/locations/view/{id}', [Admin\LocationHandler::class, 'view']);
            $r->post('/locations/view/{id}', [Admin\LocationHandler::class, 'update'], [CsrfMiddleware::class]);
            $r->delete('/locations/view/{id}', [Admin\LocationHandler::class, 'delete']);

            // Databases
            $r->get('/databases', [Admin\DatabaseHostHandler::class, 'index']);
            $r->post('/databases', [Admin\DatabaseHostHandler::class, 'create'], [CsrfMiddleware::class]);
            $r->get('/databases/view/{id}', [Admin\DatabaseHostHandler::class, 'view']);
            $r->post('/databases/view/{id}', [Admin\DatabaseHostHandler::class, 'update'], [CsrfMiddleware::class]);
            $r->delete('/databases/view/{id}', [Admin\DatabaseHostHandler::class, 'delete']);

            // Nodes
            $r->get('/nodes', [Admin\NodeHandler::class, 'index']);
            $r->get('/nodes/new', [Admin\NodeHandler::class, 'createForm']);
            $r->post('/nodes/new', [Admin\NodeHandler::class, 'create'], [CsrfMiddleware::class]);
            $r->get('/nodes/view/{id}', [Admin\NodeHandler::class, 'view']);
            $r->post('/nodes/view/{id}/settings', [Admin\NodeHandler::class, 'updateSettings'], [CsrfMiddleware::class]);
            $r->get('/nodes/view/{id}/configuration', [Admin\NodeHandler::class, 'configuration']);
            $r->get('/nodes/view/{id}/allocation', [Admin\NodeHandler::class, 'allocations']);
            $r->get('/nodes/view/{id}/allocations-json', [Admin\NodeHandler::class, 'allocationsJson']);
            $r->post('/nodes/view/{id}/allocation', [Admin\NodeHandler::class, 'createAllocation'], [CsrfMiddleware::class]);
            $r->post('/nodes/view/{id}/allocations/delete-unassigned', [Admin\NodeHandler::class, 'deleteUnassignedAllocations'], [CsrfMiddleware::class]);
            $r->delete('/nodes/view/{id}/allocation/{allocId}', [Admin\NodeHandler::class, 'deleteAllocation']);
            $r->get('/nodes/view/{id}/servers', [Admin\NodeHandler::class, 'servers']);
            $r->get('/nodes/view/{id}/system-information', [Admin\NodeHandler::class, 'systemInfo']);
            $r->delete('/nodes/view/{id}', [Admin\NodeHandler::class, 'delete']);

            // Servers
            $r->get('/servers', [Admin\ServerHandler::class, 'index']);
            $r->get('/servers/new', [Admin\ServerHandler::class, 'createForm']);
            $r->post('/servers/new', [Admin\ServerHandler::class, 'create'], [CsrfMiddleware::class]);
            $r->get('/servers/view/{id}', [Admin\ServerHandler::class, 'view']);
            $r->get('/servers/view/{id}/details', [Admin\ServerHandler::class, 'details']);
            $r->post('/servers/view/{id}/details', [Admin\ServerHandler::class, 'updateDetails'], [CsrfMiddleware::class]);
            $r->get('/servers/view/{id}/build', [Admin\ServerHandler::class, 'build']);
            $r->post('/servers/view/{id}/build', [Admin\ServerHandler::class, 'updateBuild'], [CsrfMiddleware::class]);
            $r->get('/servers/view/{id}/startup', [Admin\ServerHandler::class, 'startup']);
            $r->post('/servers/view/{id}/startup', [Admin\ServerHandler::class, 'updateStartup'], [CsrfMiddleware::class]);
            $r->get('/servers/view/{id}/database', [Admin\ServerHandler::class, 'databases']);
            $r->post('/servers/view/{id}/database', [Admin\ServerHandler::class, 'createDatabase'], [CsrfMiddleware::class]);
            $r->post('/servers/view/{id}/database/{dbId}/reset-password', [Admin\ServerHandler::class, 'resetDatabasePassword'], [CsrfMiddleware::class]);
            $r->delete('/servers/view/{id}/database/{dbId}', [Admin\ServerHandler::class, 'deleteDatabase']);
            $r->get('/servers/view/{id}/mounts', [Admin\ServerHandler::class, 'mounts']);
            $r->post('/servers/view/{id}/mounts', [Admin\ServerHandler::class, 'addMount'], [CsrfMiddleware::class]);
            $r->delete('/servers/view/{id}/mounts/{mountId}', [Admin\ServerHandler::class, 'deleteMount']);
            $r->get('/servers/view/{id}/manage', [Admin\ServerHandler::class, 'manage']);
            $r->post('/servers/view/{id}/manage/toggle-suspend', [Admin\ServerHandler::class, 'toggleSuspend'], [CsrfMiddleware::class]);
            $r->post('/servers/view/{id}/manage/toggle-install', [Admin\ServerHandler::class, 'toggleInstallStatus'], [CsrfMiddleware::class]);
            $r->post('/servers/view/{id}/manage/reinstall', [Admin\ServerHandler::class, 'reinstall'], [CsrfMiddleware::class]);
            $r->delete('/servers/view/{id}/delete', [Admin\ServerHandler::class, 'delete']);

            // Users
            $r->get('/users', [Admin\UserHandler::class, 'index']);
            $r->get('/users/new', [Admin\UserHandler::class, 'createForm']);
            $r->post('/users/new', [Admin\UserHandler::class, 'create'], [CsrfMiddleware::class]);
            $r->get('/users/view/{id}', [Admin\UserHandler::class, 'view']);
            $r->post('/users/view/{id}', [Admin\UserHandler::class, 'update'], [CsrfMiddleware::class]);
            $r->delete('/users/view/{id}', [Admin\UserHandler::class, 'delete']);

            // Nests & Eggs
            $r->get('/nests', [Admin\NestHandler::class, 'index']);
            $r->post('/nests', [Admin\NestHandler::class, 'create'], [CsrfMiddleware::class]);
            $r->get('/nests/view/{id}', [Admin\NestHandler::class, 'view']);
            $r->post('/nests/view/{id}', [Admin\NestHandler::class, 'update'], [CsrfMiddleware::class]);
            $r->delete('/nests/view/{id}', [Admin\NestHandler::class, 'delete']);
            $r->get('/nests/egg/new', [Admin\EggHandler::class, 'createForm']);
            $r->post('/nests/egg/new', [Admin\EggHandler::class, 'create'], [CsrfMiddleware::class]);
            $r->get('/nests/egg/import', [Admin\EggHandler::class, 'importForm']);
            $r->post('/nests/egg/import', [Admin\EggHandler::class, 'import'], [CsrfMiddleware::class]);
            $r->get('/nests/egg/{id}', [Admin\EggHandler::class, 'view']);
            $r->post('/nests/egg/{id}', [Admin\EggHandler::class, 'update'], [CsrfMiddleware::class]);
            $r->post('/nests/egg/{id}/clone', [Admin\EggHandler::class, 'cloneEgg'], [CsrfMiddleware::class]);
            $r->get('/nests/egg/{id}/export', [Admin\EggHandler::class, 'export']);
            $r->get('/nests/egg/{id}/variables', [Admin\EggHandler::class, 'variables']);
            $r->post('/nests/egg/{id}/variables', [Admin\EggHandler::class, 'createVariable'], [CsrfMiddleware::class]);
            $r->post('/nests/egg/{id}/variables/{varId}', [Admin\EggHandler::class, 'updateVariable'], [CsrfMiddleware::class]);
            $r->delete('/nests/egg/{id}/variables/{varId}', [Admin\EggHandler::class, 'deleteVariable']);
            $r->delete('/nests/egg/{id}', [Admin\EggHandler::class, 'delete']);

            // Mounts
            $r->get('/mounts', [Admin\MountHandler::class, 'index']);
            $r->post('/mounts', [Admin\MountHandler::class, 'create'], [CsrfMiddleware::class]);
            $r->get('/mounts/view/{id}', [Admin\MountHandler::class, 'view']);
            $r->post('/mounts/view/{id}', [Admin\MountHandler::class, 'update'], [CsrfMiddleware::class]);
            $r->post('/mounts/{id}/eggs', [Admin\MountHandler::class, 'addEggs']);
            $r->delete('/mounts/{id}/eggs/{eggId}', [Admin\MountHandler::class, 'deleteEgg']);
            $r->post('/mounts/{id}/nodes', [Admin\MountHandler::class, 'addNodes']);
            $r->delete('/mounts/{id}/nodes/{nodeId}', [Admin\MountHandler::class, 'deleteNode']);
            $r->delete('/mounts/view/{id}', [Admin\MountHandler::class, 'delete']);

            // Settings
            $r->get('/settings', [Admin\SettingsHandler::class, 'index']);
            $r->post('/settings', [Admin\SettingsHandler::class, 'updateGeneral'], [CsrfMiddleware::class]);
            $r->get('/settings/mail', [Admin\SettingsHandler::class, 'mail']);
            $r->post('/settings/mail', [Admin\SettingsHandler::class, 'updateMail'], [CsrfMiddleware::class]);
            $r->get('/settings/advanced', [Admin\SettingsHandler::class, 'advanced']);
            $r->post('/settings/advanced', [Admin\SettingsHandler::class, 'updateAdvanced'], [CsrfMiddleware::class]);

            // API Keys
            $r->get('/api', [Admin\ApiHandler::class, 'index']);
            $r->get('/api/new', [Admin\ApiHandler::class, 'createForm']);
            $r->post('/api/new', [Admin\ApiHandler::class, 'create'], [CsrfMiddleware::class]);
            $r->delete('/api/{id}', [Admin\ApiHandler::class, 'delete']);
        });
    }
}
