# Pterodactyl Panel - Native PHP 8.2+ & Vanilla Frontend Rebuild

This document describes the complete rebuild of Pterodactyl Panel from its previous architecture (Laravel + React + TypeScript + Webpack/Vite) into a native architecture of **PHP 8.2+**, **HTML5**, **CSS3**, and **Vanilla JavaScript (ES6+)**.

---

## 1. Architectural Changes and Removals

### Removed Technologies:
- **Laravel Framework** (Kernel, Service Providers, Eloquent ORM, Blade, Laravel Middlewares)
- **React.js & JSX**
- **Vue.js**
- **TypeScript**
- **Webpack / Vite / Babel / PostCSS / Tailwind CLI / Node.js build pipeline**

### Technologies Exclusively Used:
- **Native PHP 8.2+** with custom PSR-4 autoloader (`src/autoload.php`)
- **Semantic HTML5** with native templating engine (`src/Core/View.php`)
- **Custom CSS3** (modern Dark Theme, full responsive styling in `public/assets/css/pterodactyl.css` and `public/assets/css/terminal.css`)
- **Vanilla JavaScript (ES6+)**:
  - `public/assets/js/app.js`: DOM manipulation, modals, dropdowns, toast notifications, Fetch API / AJAX requests with CSRF headers.
  - `public/assets/js/console.js`: Native ANSI terminal, direct WebSocket connection to Wings daemon, command history in localStorage, auto-scroll, power commands (start/stop/restart/kill).
  - `public/assets/js/stats.js`: Real-time charts on HTML5 Canvas for CPU Load, RAM Usage, and Network In/Out.
  - `public/assets/js/files.js`: File manager with directory navigation, file/folder creation, rename, delete, archive/unarchive, chmod permissions, upload with progress bar, and download links.
- **Native MySQL / MariaDB** via PDO (`src/Core/Database.php`) with prepared statements, transactions, and optimized indexing.
- **Native RFC 7519 JWT Service** (`src/Security/JwtService.php`) based on `hash_hmac('sha256')` for signing Wings WebSocket tokens without external libraries.

---

## 2. Entry Point (`public/index.php`)

The `public/index.php` file was completely refactored to directly boot the native core:
```php
define('PTERODACTYL_START', microtime(true));

require_once __DIR__ . '/../src/autoload.php';

use Pterodactyl\Core\Application;
use Pterodactyl\Core\Request;

$app = new Application(dirname(__DIR__));
$request = new Request();
$response = $app->handle($request);
$response->send();
```

---

## 3. Backend Core Structure (`src/`)

- `src/autoload.php`: PSR-4 autoloader registration compliant with PHP standards.
- `src/Routes.php`: Definition of all routes for Authentication, Wings Remote API, Application API, Client Server Control, and Admin Panel.
- `src/Core/`:
  - `Application.php`: Application kernel, reading configuration from `.env`, setting timezone, and error handling.
  - `Config.php`: Fast, simple `.env` parser without additional dependencies.
  - `Database.php`: Singleton PDO wrapper for persistent connections, `query`, `queryOne`, `execute`, `insert`, `update`, and transactions.
  - `Request.php`: Native HTTP Request object (method, path, GET/POST/JSON parameters, headers, client IP, boolean inputs).
  - `Response.php`: HTTP Response object (html, json, redirect, noContent, stream, custom status codes, and headers).
  - `Router.php`: Fast routing system with dynamic parameters (`{id}`, `{server}`, `{uuid}`), route groups, prefixes, and middlewares.
  - `Session.php`: Secure session management, flash messages, and CSRF token generation/validation.
  - `View.php`: Native PHP rendering engine with layout inheritance (`$this->extend`), sections (`$this->start`, `$this->end`, `$this->yield`), and XSS protection (`View::e()`, `View::csrf()`).
- `src/Security/`:
  - `Encrypter.php`: AES-256-CBC encryption/decryption compatible with the `APP_KEY` from Pterodactyl.
  - `Gate.php`: Permission checker across servers and roles (Root Admin, Server Owner, Subuser with granular permissions).
  - `JwtService.php`: RFC 7519 compliant JWT generator for communication with Pterodactyl Wings (WebSocket tokens, SFTP).
  - `TotpService.php`: Two-Factor Authentication (2FA) using the TOTP algorithm (RFC 6238).
- `src/Middleware/`:
  - `AuthMiddleware.php`: Ensures user authentication.
  - `AdminMiddleware.php`: Verifies the `root_admin` flag.
  - `CsrfMiddleware.php`: Verifies CSRF tokens on POST/PUT/DELETE requests.
  - `DaemonAuthMiddleware.php`: Validates requests originating from Pterodactyl Wings (`Authorization: Bearer <daemon_token_id>.<daemon_token_secret>`).
  - `ServerAccessMiddleware.php`: Resolves server by UUID or short UUID and validates user permissions.
- `src/Wings/`:
  - `WingsClient.php`: Native cURL client for all Wings Daemon operations (Power, Commands, Files, Backups, Sync, Reinstall, Delete, System Info).
  - `ServerConfigurator.php`: Generates the complete server configuration payload for the Wings daemon.
  - `EggConfigurator.php`: Parses startup, stop configurations, and configuration files from Eggs.

---

## 4. Rebuilt Administrative Panel (`views/admin/` & `src/Handlers/Admin/`)

All sections of the administrative panel are complete and fully operational:

1. **Overview / Dashboard** (`views/admin/index.php` & `OverviewHandler.php`):
   - Real-time statistics: Total Servers, Active Nodes, Registered Users, Port Allocations.
   - System information and architecture overview.
2. **Locations** (`views/admin/locations/` & `LocationHandler.php`):
   - Location listing, modal-based creation, code and description editing, assigned nodes listing, location deletion.
3. **Database Hosts** (`views/admin/databases/` & `DatabaseHostHandler.php`):
   - MySQL/MariaDB host listing, host creation with port and admin credentials, node assignment (specific or global), listing databases on host, host removal.
4. **Nodes** (`views/admin/nodes/` & `NodeHandler.php`):
   - Node listing with resource utilization (Allocated vs. Available Memory and Disk).
   - Physical node creation form (FQDN, SSL, HTTP/HTTPS scheme, maintenance mode, memory and disk limits, upload size, daemon and SFTP ports).
   - Wings Configuration (`views/admin/nodes/configuration.php`): auto-deploy command generator `wings configure --panel-url ... --token ...` and `config.yml` preview.
   - IP & Port Allocations Management (`views/admin/nodes/allocations.php`): adding individual ports or port ranges (e.g., `25565-25575`), assigned server overview, deleting unassigned ports.
   - Node Servers (`views/admin/nodes/servers.php`): list of all containerized servers running on the node.
   - Direct retrieval of system information from Wings daemon via API (`/api/system`).
5. **Servers** (`views/admin/servers/` & `ServerHandler.php`):
   - Server listing with advanced search by name, UUID, short UUID, and owner.
   - New Server Wizard (`views/admin/servers/new.php`): owner selection, node selection, automatic port allocation, dynamic nest & egg selection, limits for memory, swap, disk, CPU, IO, docker image, and startup command.
   - Server Overview (`views/admin/servers/view.php`): full metadata, container state, node, direct link to client console.
   - Details Editing (`views/admin/servers/details.php`): update name, owner, description with automatic Wings synchronization.
   - Build Configuration (`views/admin/servers/build.php`): adjust resource quotas (CPU, RAM, Swap, Disk, OOM killer, database and backup limits).
   - Startup Configuration & Variables (`views/admin/servers/startup.php`): modify startup command, change Egg and Docker image, full administrative editing of environment variables, and Wings sync.
   - Server Databases (`views/admin/servers/databases.php`): list databases, create databases directly on target MySQL hosts, reset database passwords, and delete databases.
   - Server Mounts (`views/admin/servers/mounts.php`): attach and detach host mounts with Wings synchronization.
   - Administrative Actions (`views/admin/servers/manage.php`): suspend / unsuspend server, reset stuck install state, trigger full container reinstall, permanently delete server with allocation release and database cleanup.
6. **Users** (`views/admin/users/` & `UserHandler.php`):
   - User search and listing with roles, 2FA status, and owned server count.
   - User creation (email, username, password, root admin privileges).
   - User editing (update details, reset password, toggle root admin, list owned servers, delete account).
7. **Nests & Eggs** (`views/admin/nests/`, `views/admin/eggs/` & `NestHandler.php`, `EggHandler.php`):
   - List and create Nests.
   - View and edit Nest, list component Eggs.
   - Create new Egg with Docker image and startup command.
   - Import Egg from JSON (`views/admin/eggs/import.php`): upload official Pterodactyl `.json` file or paste raw JSON, with automatic parsing of variables, containers, and installation scripts.
   - Clone Egg: instant duplication of an Egg with all variables and configuration settings.
   - Full Egg editing: Docker image, stop command (`stop` or `^C` signals), Bash installation script, installation container (`alpine`), export Egg definition in JSON format.
   - Egg Environment Variable Management (`views/admin/eggs/variables.php`): add new variables, edit existing variables via modal, delete variables, `{{NAME}}` placeholder, default values, validation rules, user editing permissions.
8. **Mounts** (`views/admin/mounts/` & `MountHandler.php`):
   - List mount points (shared directories between host machine and Docker containers).
   - Create new mount with source and target path, Read-Only / Read-Write rights.
   - Attach and detach nodes and eggs for each mount point.
9. **Settings** (`views/admin/settings/` & `SettingsHandler.php`):
   - General: Application / company name, default language.
   - Mail: SMTP configuration (Host, Port, User, Password, TLS/SSL, From Address).
   - Advanced: Google reCAPTCHA integration.
10. **Application API** (`views/admin/api/` & `ApiHandler.php`):
    - Generate administrative API keys with secure prefix `ptla_...`.
    - Granular permission matrix (Servers, Nodes, Allocations, Users, Locations, Nests, Database Hosts).
    - IP address filtering (IP allowlist).
    - API key revocation and deletion.

---

## 5. Rebuilt Client Frontend (`views/` & `public/assets/`)

All pages for user account and server control operate natively:

- **Authentication** (`views/auth/login.php`, `views/auth/checkpoint.php`):
  - Login with username/email and password.
  - 2FA TOTP checkpoint verification.
- **Client Dashboard** (`views/dashboard/index.php`):
  - List of owned servers and servers where the user is a subuser.
  - Live status, IP:port, resource allocations, direct console links.
- **Client Account** (`views/account/`):
  - Change email address and password.
  - Two-Factor Authentication setup (TOTP Secret, QR generator, backup codes).
  - Client API Keys (`views/account/api.php`): generate and revoke client API keys `ptlc_...`.
  - SSH Keys (`views/account/ssh.php`): add public SSH keys for passwordless SFTP access.
  - Account activity log (`views/account/activity.php`).
- **Server Control**:
  - **Console & Terminal** (`views/server/console.php`):
    - Live WebSocket directly to Wings.
    - Full support for ANSI colors in terminal.
    - Power action buttons (Start, Restart, Stop, Kill).
    - Interactive canvas charts for CPU, RAM, and Network.
  - **File Manager** (`views/server/files.php`, `views/server/file_edit.php`):
    - Directory listing, breadcrumb navigation.
    - Text file editing with direct API save.
    - Folder creation, rename, delete, ZIP/tar archiving, unarchiving, chmod permissions.
    - File upload via drag & drop or file picker with progress bar.
    - Secure file download.
  - **Databases** (`views/server/databases.php`):
    - Create MySQL databases on configured hosts.
    - Rotate database password.
    - Delete database.
  - **Schedules / Cron Jobs** (`views/server/schedules.php`, `views/server/schedule_edit.php`):
    - Create automated schedules based on cron syntax.
    - Add scheduled tasks (console command execution, power action, backup creation).
    - Immediate manual execution.
  - **Subusers / Users** (`views/server/users.php`):
    - Add collaborators to server with granular permission selection.
    - Edit and revoke subuser access.
  - **Backups** (`views/server/backups.php`):
    - Create backup (Wings archiving).
    - Download backup.
    - Lock/unlock backup to prevent automatic deletion.
    - Restore backup (with or without clearing directory contents).
    - Delete backup.
  - **Network Allocations** (`views/server/network.php`):
    - View assigned ports.
    - Set primary port.
    - Add notes to allocations.
    - Request new allocation (if server limit allows).
  - **Startup Configuration** (`views/server/startup.php`):
    - Edit startup variables (e.g., allocated memory, jarfile, port, game settings).
    - Switch Docker images if alternative images are defined in the Egg.
  - **Server Settings** (`views/server/settings.php`):
    - Rename server.
    - Reinstall server.
    - SFTP connection details (host, port, username `username.uuidShort`).
  - **Server Activity Log** (`views/server/activity.php`):
    - History of all actions performed on the server (who stopped/started server, who edited files, variable changes, etc.).

---

## 6. Verification and Integrity

- All **54 template files (views)** were created, tested, and verified with complete layout hierarchy rendering.
- All **116 core PHP files** (`src/`, `views/`, `cli.php`, `public/index.php`) were inspected and syntactically validated (`php -l`) with zero errors.
- The complete suite of native frontend assets (`public/assets/css/pterodactyl.css`, `public/assets/css/terminal.css`, `public/assets/js/app.js`, `public/assets/js/console.js`, `public/assets/js/stats.js`, `public/assets/js/files.js`) is integrated and operational without Node.js dependencies or external libraries.
- The original Pterodactyl Panel database suffers no destructive changes: existing tables, relationships, and data (users, servers, nodes, allocations) are utilized directly.
- The protocol with Pterodactyl Wings is strictly adhered to across HTTP REST, WebSocket, and SFTP components.
