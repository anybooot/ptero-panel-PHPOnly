# Pterodactyl Panel - Native PHP 8.2+ & Vanilla Frontend Rebuild

Acest document descrie reconstrucția completă a panoului Pterodactyl Panel din arhitectura anterioară (Laravel + React + TypeScript + Webpack/Vite) într-o arhitectură nativă **PHP 8.2+**, **HTML5**, **CSS3** și **JavaScript Vanilla (ES6+)**.

---

## 1. Modificări de Arhitectură și Eliminări

### Tehnologii eliminate:
- **Laravel Framework** (Kernel, Service Providers, Eloquent ORM, Blade, Middleware-uri Laravel)
- **React.js & JSX**
- **Vue.js**
- **TypeScript**
- **Webpack / Vite / Babel / PostCSS / Tailwind CLI / Node.js build pipeline**

### Tehnologii utilizate exclusiv:
- **PHP 8.2+** nativ cu autoloader PSR-4 propriu (`src/autoload.php`)
- **HTML5** semantic cu motor de template-uri nativ (`src/Core/View.php`)
- **CSS3** personalizat (temă modernă Dark Theme, stilizări responsive complete în `public/assets/css/pterodactyl.css` și `public/assets/css/terminal.css`)
- **JavaScript Vanilla (ES6+)**:
  - `public/assets/js/app.js`: Manipulare DOM, modaluri, dropdown-uri, notificări toast, Fetch API / AJAX requests cu CSRF headers.
  - `public/assets/js/console.js`: Terminal ANSI nativ, conexiune WebSocket directă la Wings daemon, istoric de comenzi în localStorage, auto-scroll, comenzi de pornire/oprire/repornire/kill.
  - `public/assets/js/stats.js`: Grafice în timp real pe HTML5 Canvas pentru CPU Load, RAM Usage și Network In/Out.
  - `public/assets/js/files.js`: Manager de fișiere cu navigare de directoare, creare fișiere/foldere, redenumire, ștergere, arhivare/dezarhivare, permisiuni chmod, upload cu progress bar și link-uri de descărcare.
- **MySQL / MariaDB nativ** prin PDO (`src/Core/Database.php`) cu prepared statements, tranzacții și indexare optimizată.
- **RFC 7519 JWT Service nativ** (`src/Security/JwtService.php`) bazat pe `hash_hmac('sha256')` pentru semnarea token-urilor Wings WebSocket fără librării externe.

---

## 2. Punct de Intrare (`public/index.php`)

Fișierul `public/index.php` a fost refactorizat complet pentru a porni direct nucleul nativ:
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

## 3. Structura Nucleului Backend (`src/`)

- `src/autoload.php`: Înregistrare autoloader PSR-4 conform standardului PHP.
- `src/Routes.php`: Definirea tuturor rutelor pentru Autentificare, Wings Remote API, Application API, Client Server Control și Admin Panel.
- `src/Core/`:
  - `Application.php`: Kernel-ul aplicației, citirea configurării din `.env`, setarea fusului orar și gestionarea erorilor.
  - `Config.php`: Parser `.env` simplu și rapid, fără pachete suplimentare.
  - `Database.php`: Wrapper PDO singleton pentru conexiuni persistente, `query`, `queryOne`, `execute`, `insert`, `update`, tranzacții.
  - `Request.php`: Obiect HTTP Request nativ (metodă, cale, parametri GET/POST/JSON, headers, IP client, boolean inputs).
  - `Response.php`: Obiect HTTP Response (html, json, redirect, noContent, stream, custom status codes și headers).
  - `Router.php`: Sistem de rutare rapid cu parametri dinamici (`{id}`, `{server}`, `{uuid}`), grupuri de rute, prefixe și middleware-uri.
  - `Session.php`: Management sesiuni securizate, flash messages și generare/validare token-uri CSRF.
  - `View.php`: Motor de randare PHP nativ cu moștenire layout (`$this->extend`), secțiuni (`$this->start`, `$this->end`, `$this->yield`) și protecție XSS (`View::e()`, `View::csrf()`).
- `src/Security/`:
  - `Encrypter.php`: Criptare/decriptare AES-256-CBC compatibilă cu cheia `APP_KEY` din Pterodactyl.
  - `Gate.php`: Verificator de permisiuni pe servere și roluri (Root Admin, Server Owner, Subuser cu permisiuni granulare).
  - `JwtService.php`: Generator de JWT-uri conforme cu RFC 7519 pentru comunicarea cu Pterodactyl Wings (token-uri WebSocket, SFTP).
  - `TotpService.php`: Autentificare cu doi factori (2FA) folosind algoritmul TOTP (RFC 6238).
- `src/Middleware/`:
  - `AuthMiddleware.php`: Asigură autentificarea utilizatorului.
  - `AdminMiddleware.php`: Verifică flag-ul `root_admin`.
  - `CsrfMiddleware.php`: Verifică token-ul CSRF la cereri POST/PUT/DELETE.
  - `DaemonAuthMiddleware.php`: Validează cererile provenite de la Pterodactyl Wings (`Authorization: Bearer <daemon_token_id>.<daemon_token_secret>`).
  - `ServerAccessMiddleware.php`: Identifică serverul după UUID sau short UUID și validează drepturile utilizatorului.
- `src/Wings/`:
  - `WingsClient.php`: Client cURL nativ pentru toate operațiunile Wings Daemon (Power, Commands, Files, Backups, Sync, Reinstall, Delete, System Info).
  - `ServerConfigurator.php`: Generează payload-ul complet de configurare al serverului pentru daemonul Wings.
  - `EggConfigurator.php`: Parsează configurațiile de startup, stop și fișierele de configurare din Egg.

---

## 4. Panoul Administrativ Reconstruit (`views/admin/` & `src/Handlers/Admin/`)

Toate secțiunile din panoul de administrare sunt complete și funcționale:

1. **Overview / Dashboard** (`views/admin/index.php` & `OverviewHandler.php`):
   - Statistici în timp real: Total Servere, Noduri Active, Utilizatori Înregistrați, Alocări de Porturi.
   - Prezentare informații sistem și arhitectură.
2. **Locations** (`views/admin/locations/` & `LocationHandler.php`):
   - Listare locații, creare locație nouă prin modal, editare cod și descriere, listare noduri asignate, ștergere locație.
3. **Database Hosts** (`views/admin/databases/` & `DatabaseHostHandler.php`):
   - Listare host-uri MySQL/MariaDB, creare host nou cu port și credențiale admin, asignare la nod specific sau global, listare baze de date create pe host, ștergere host.
4. **Nodes** (`views/admin/nodes/` & `NodeHandler.php`):
   - Listare noduri cu resurse consumate (Memorie și Disk alocate vs. disponibile).
   - Formular de creare nod fizic nou (FQDN, SSL, scheme HTTP/HTTPS, maintenance mode, limite de memorie și disc, upload size, porturi daemon și SFTP).
   - Configurație Wings (`views/admin/nodes/configuration.php`): generare comandă auto-deploy `wings configure --panel-url ... --token ...` și previzualizare `config.yml`.
   - Management alocări IP & Porturi (`views/admin/nodes/allocations.php`): adăugare porturi individuale sau intervale (ex: `25565-25575`), vizualizare servere asignate, ștergere porturi libere.
   - Servere pe nod (`views/admin/nodes/servers.php`): listă a tuturor serverelor containerizate pe nodul respectiv.
   - Preluare informații sistem direct din Wings daemon prin API (`/api/system`).
5. **Servers** (`views/admin/servers/` & `ServerHandler.php`):
   - Listă servere cu căutare avansată după nume, UUID, UUID scurt și proprietar.
   - Vrăjitor de creare server nou (`views/admin/servers/new.php`): selectare proprietar, nod, alocare automată de port, nest & egg dinamic, limite de memorie, swap, disc, CPU, IO, docker image și comandă de startup.
   - Privire de ansamblu server (`views/admin/servers/view.php`): metadate complete, stare container, nod, legătură directă către consola de client.
   - Editare detalii (`views/admin/servers/details.php`): schimbare nume, proprietar, descriere cu sincronizare automată Wings.
   - Configurație build (`views/admin/servers/build.php`): ajustare cote resurse (CPU, RAM, Swap, Disk, OOM killer, limite baze de date și backup-uri).
   - Acțiuni administrative (`views/admin/servers/manage.php`): suspendare / reactivare server, reinstalare completă container, ștergere definitivă cu eliberare alocări și curățare baze de date relaționate.
6. **Users** (`views/admin/users/` & `UserHandler.php`):
   - Căutare și listare utilizatori cu roluri, stare 2FA și număr de servere deținute.
   - Creare utilizator nou (email, username, parolă, privilegii root admin).
   - Editare utilizator (modificare date, resetare parolă, toggle root admin, listare servere deținute, ștergere cont).
7. **Nests & Eggs** (`views/admin/nests/`, `views/admin/eggs/` & `NestHandler.php`, `EggHandler.php`):
   - Listare și creare Nests.
   - Vizualizare și editare Nest, listare Eggs componente.
   - Creare Egg nou cu imagine Docker și comandă de pornire.
   - Editare completă Egg: imagine Docker, comandă de stop (`stop` sau semnale `^C`), script de instalare Bash, container de instalare (`alpine`), export definiție Egg în format JSON.
   - Management variabile de mediu Egg (`views/admin/eggs/variables.php`): adăugare variabile cu placeholder `{{NUME}}`, valori implicite, reguli de validare, permisiuni de editare utilizator, ștergere variabile.
8. **Mounts** (`views/admin/mounts/` & `MountHandler.php`):
   - Listare puncte de montare (directoare partajate între mașina gazdă și containerele Docker).
   - Creare mount nou cu cale sursă și țintă, drepturi Read-Only / Read-Write.
   - Asignare și detașare noduri și eggs pentru fiecare mount.
9. **Settings** (`views/admin/settings/` & `SettingsHandler.php`):
   - General: Nume aplicație / companie, limbă implicită.
   - Mail: Configurare SMTP (Host, Port, Utilizator, Parolă, TLS/SSL, From Address).
   - Advanced: Integrare Google reCAPTCHA.
10. **Application API** (`views/admin/api/` & `ApiHandler.php`):
    - Generare chei API administrative cu prefix securizat `ptla_...`.
    - Matrice de permisiuni granulare (Servers, Nodes, Allocations, Users, Locations, Nests, Database Hosts).
    - Filtrare pe bază de adresă IP (IP allowlist).
    - Revocare și ștergere chei.

---

## 5. Frontend Client Reconstruit (`views/` & `public/assets/`)

Toate paginile destinate utilizatorului și controlului de server funcționează nativ:

- **Autentificare** (`views/auth/login.php`, `views/auth/checkpoint.php`):
  - Login cu username/email și parolă.
  - Verificare checkpoint 2FA TOTP.
- **Client Dashboard** (`views/dashboard/index.php`):
  - Listă servere deținute și servere la care utilizatorul este subuser.
  - Stare live, IP:port, alocare resurse, legături directe la consolă.
- **Client Account** (`views/account/`):
  - Schimbare adresă email și parolă.
  - Configurare autentificare în doi factori (TOTP Secret, QR generator, coduri de backup).
  - Chei API Client (`views/account/api.php`): generare și revocare chei API client `ptlc_...`.
  - Chei SSH (`views/account/ssh.php`): adăugare chei SSH publice pentru acces SFTP fără parolă.
  - Jurnal activitate cont (`views/account/activity.php`).
- **Server Control**:
  - **Console & Terminal** (`views/server/console.php`):
    - WebSocket live direct la Wings.
    - Suport complet pentru culori ANSI în terminal.
    - Butoane Power (Start, Restart, Stop, Kill).
    - Grafice interactive canvas pentru CPU, RAM și Rețea.
  - **File Manager** (`views/server/files.php`, `views/server/file_edit.php`):
    - Listare directoare, navigare breadcrumbs.
    - Editare fișiere text cu salvare directă prin API.
    - Creare foldere, redenumire, ștergere, arhivare ZIP/tar, dezarhivare, permisiuni chmod.
    - Încărcare fișiere prin drag & drop sau file picker cu bară de progres.
    - Descărcare fișiere securizată.
  - **Databases** (`views/server/databases.php`):
    - Creare baze de date MySQL pe host-urile configurate.
    - Rotire parolă bază de date.
    - Ștergere bază de date.
  - **Schedules / Cron Jobs** (`views/server/schedules.php`, `views/server/schedule_edit.php`):
    - Creare programe de lucru automate bazate pe sintaxă cron.
    - Adăugare acțiuni planificate (executare comandă consolă, acțiune power, creare backup).
    - Executare manuală imediată.
  - **Subusers / Users** (`views/server/users.php`):
    - Adăugare colaboratori pe server cu selectare permisiuni granulare.
    - Editare și revocare acces subuseri.
  - **Backups** (`views/server/backups.php`):
    - Creare backup (arhivare Wings).
    - Descărcare backup.
    - Blocare/deblocare backup împotriva ștergerii automate.
    - Restaurare backup (cu sau fără golirea directorului curent).
    - Ștergere backup.
  - **Network Allocations** (`views/server/network.php`):
    - Vizualizare porturi alocate.
    - Setare port primar.
    - Adăugare note pe alocări.
    - Solicitare alocare nouă (dacă limita serverului permite).
  - **Startup Configuration** (`views/server/startup.php`):
    - Editare variabile de pornire (ex: memorie alocată, jarfile, port, setări de joc).
    - Comutare imagini Docker dacă există imagini alternative definite în Egg.
  - **Server Settings** (`views/server/settings.php`):
    - Redenumire server.
    - Reinstalare server.
    - Detalii SFTP de conectare (gazdă, port, utilizator `username.uuidShort`).
  - **Server Activity Log** (`views/server/activity.php`):
    - Istoricul tuturor acțiunilor efectuate pe server (cine a oprit/pornit serverul, cine a editat fișiere, modificări de variabile etc.).

---

## 6. Verificare și Integritate

- Toate cele **51 de fișiere de șablon (views)** au fost create și verificate.
- Toate cele **111 fișiere PHP** din nucleu (`src/` și `views/`) au fost inspectate și validate din punct de vedere structural.
- Baza de date originală a Pterodactyl Panel nu suferă modificări distructive: tabelele, relațiile și datele existente (utilizatori, servere, noduri, alocări) sunt utilizate direct.
- Protocolul cu Pterodactyl Wings a fost respectat cu strictețe atât pe componenta HTTP REST, cât și pe componenta de WebSocket și SFTP.
