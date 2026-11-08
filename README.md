# Ptero Panel PHPOnly

<p align="center">
  <strong>PTERO PANEL PHPONLY</strong>
  <br>
  <em>A PHP-powered alternative to Pterodactyl Panel.</em>
  <br><br>
  Rebuilt using PHP, HTML5, CSS3 and Vanilla JavaScript.
</p>

<p align="center">
  https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white
  https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white
  https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white
  https://img.shields.io/badge/JavaScript-Vanilla-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black
  https://img.shields.io/badge/MySQL-MariaDB-4479A1?style=for-the-badge&logo=mysql&logoColor=white
</p>

---

## About

**Ptero Panel PHPOnly** is an alternative implementation of the Pterodactyl Panel, focused on replacing its original React and Laravel architecture with a pure PHP-based implementation.

The project aims to preserve the original Pterodactyl ecosystem and infrastructure while rebuilding the panel using:

- PHP 8.2+
- HTML5
- CSS3
- Vanilla JavaScript
- MySQL / MariaDB

The goal is to maintain compatibility with existing Pterodactyl infrastructure, including Wings, Docker, WebSockets, databases, server configurations and communication protocols.

### Core principles

- No React.
- No Laravel.
- No Vue.
- No frontend frameworks.
- No Node.js frontend dependencies.
- Native PHP backend.
- Existing Pterodactyl infrastructure compatibility.

## Technology Stack

| Component | Technology |
|---|---|
| Backend | PHP |
| Frontend | HTML5 |
| Styling | CSS3 |
| JavaScript | Vanilla JavaScript |
| Database | MySQL / MariaDB |
| Database connection | PDO |
| Server management | Pterodactyl Wings |
| Container management | Docker |
| Real-time communication | WebSockets |
| HTTP communication | PHP / Fetch API |

---

# Installation Guide

Follow these instructions to install and configure Ptero Panel PHPOnly on your hosting or VPS.

## 1. Hosting Requirements

Your hosting provider must support PHP and have the following standard PHP extensions enabled.

### PHP Version

- PHP 8.2 or newer (PHP 8.3+ recommended).

### Required PHP Extensions

Make sure the following extensions are enabled:

- `pdo` and `pdo_mysql` – Database connectivity.
- `curl` – Communication with Wings.
- `openssl` – Encryption and token signing.
- `json` – JSON processing.
- `mbstring` – Multibyte string handling.
- `session` – Session management.

Depending on the enabled features, additional PHP extensions may be required.

### Database

- MySQL 8.0+ or MariaDB.
- A database and database user with the required permissions.

### Web Server

Supported environments:

- Apache with `mod_rewrite`.
- Nginx with PHP-FPM.
- cPanel / Shared Hosting with PHP support.

---

## 2. Uploading the Files

Download or clone the repository and upload the project files to your hosting.

### VPS / Nginx

Configure your Nginx Document Root to point directly to the project's `public/` directory.

Example:

```nginx
root /var/www/ptero-panel/public;
```

Make sure PHP-FPM is configured correctly.

### cPanel / Shared Hosting

Upload the project files to your hosting directory.

If your hosting uses `public_html`, follow the project's directory structure and ensure that the web server routes public requests through the `public/` directory.

The root `.htaccess` file can redirect requests to `public/` on compatible Apache hosting.

**Important:** Never expose internal application files, configuration files, logs or `.env` through the public web directory.

---

## 3. Environment Configuration

Create your `.env` file by copying `.env.example`.

Example:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nume_baza_de_date
DB_USERNAME=utilizator_mysql
DB_PASSWORD=parola_secreta

APP_URL=https://domeniul-tau.ro
```

Replace the example values with your actual database credentials and domain.

| Variable | Description |
|---|---|
| DB_HOST | MySQL server address |
| DB_PORT | MySQL port |
| DB_DATABASE | Database name |
| DB_USERNAME | Database username |
| DB_PASSWORD | Database password |
| APP_URL | Your panel URL |

Keep your `.env` file private and never upload it to a public GitHub repository.

---

## 4. Database Setup

### If you already have Pterodactyl installed

If you are migrating from an existing Pterodactyl installation:

1. Back up your existing database.
2. Configure the existing database credentials in `.env`.
3. Verify that the PHP application supports your existing database schema.
4. Test the panel with your existing data.

The panel is intended to preserve existing users, servers, nodes and configurations.

**Do not import `mysql-schema.sql` over an existing database.** Do not run destructive migrations or recreate existing tables.

### If this is a completely fresh installation

1. Open phpMyAdmin or your MySQL console.
2. Create a new database.
3. Import the `mysql-schema.sql` file.
4. Configure the database credentials in `.env`.

Example:

```sql
CREATE DATABASE ptero_panel;
```

Import the schema into the newly created database.

---

## 5. Creating the Administrator Account

For a fresh installation, open a terminal in the project directory and run:

```bash
php cli.php user:make
```

Follow the prompts to create your first administrator account.

### Loading Default Eggs

To import the default game configurations, including Minecraft, Rust, CS:GO and others, run:

```bash
php cli.php db:seed-eggs
```

This command should be used according to the project's database initialization requirements.

---

## 6. Wings Configuration

Ptero Panel PHPOnly is designed to communicate with the existing Pterodactyl Wings infrastructure.

Make sure that:

- Wings is installed and running on your nodes.
- Your nodes are configured correctly.
- The panel has the correct Wings API credentials.
- The configured Wings URLs are reachable.
- Firewall rules allow the required communication.
- WebSocket connections are permitted.

The panel must use the existing Wings communication protocols.

**You do not need to reinstall Docker or recreate your existing game servers just to replace the panel**, provided compatibility with your existing installation has been verified.

For more information:

- [Wings Documentation](https://pterodactyl.io/wings/1.0/installing.html)

---

## 7. Final Checks

Before using the panel, verify the following:

- [ ] PHP version is supported.
- [ ] Required PHP extensions are enabled.
- [ ] Database credentials are correct.
- [ ] `.env` is configured.
- [ ] Database schema is initialized.
- [ ] Administrator account exists.
- [ ] Web server points to the correct public directory.
- [ ] Wings API connectivity works.
- [ ] WebSocket connections work.
- [ ] File permissions are configured securely.
- [ ] HTTPS is enabled.

---

## Troubleshooting

### Blank page or HTTP 500

Check:

- PHP version.
- Required extensions.
- `.env` configuration.
- Database connection.
- PHP error logs.
- Web server error logs.

### Database connection errors

Verify:

- Database hostname.
- Database port.
- Database name.
- Username and password.
- MySQL permissions.

### Wings connection errors

Verify:

- Wings is running.
- API credentials are correct.
- Node configuration is correct.
- Firewall allows communication.
- SSL certificates are valid when using HTTPS.

### WebSocket connection errors

Check your reverse proxy, firewall and WebSocket configuration.

---

# Features

The project aims to provide the core functionality of the original Pterodactyl Panel.

### User Panel

- Dashboard.
- Server management.
- Server console.
- Live resource statistics.
- File manager.
- File uploads and downloads.
- Startup configuration.
- Database management.
- Backups.
- Schedules.
- Account settings.
- API management.

### Administration

- User management.
- Server management.
- Node management.
- Location management.
- Nest management.
- Egg management.
- Allocation management.
- System configuration.

### Infrastructure

- Pterodactyl Wings integration.
- Docker compatibility.
- MySQL / MariaDB.
- WebSocket communication.
- Server power controls.
- Resource monitoring.
- File operations.

---

# Original Pterodactyl Project

Ptero Panel PHPOnly is built around the Pterodactyl ecosystem.

Useful resources:

- [Pterodactyl Website](https://pterodactyl.io)
- [Original Pterodactyl Repository](https://github.com/pterodactyl/panel)
- [Panel Documentation](https://pterodactyl.io/panel/1.0/getting_started.html)
- [Wings Documentation](https://pterodactyl.io/wings/1.0/installing.html)
- [Pterodactyl Discord](https://discord.gg/pterodactyl)

---

# Project Status

Development is ongoing.

The project focuses on rebuilding the original panel architecture using PHP while maintaining compatibility with the Pterodactyl ecosystem.

Features should be considered complete only after implementation and testing.

---

# License & Credits

Pterodactyl is an open-source project originally created by Dane Everitt and its contributors.

Ptero Panel PHPOnly is an independent project and is not an official Pterodactyl release.

Pterodactyl trademarks and original code remain subject to their respective ownership and license terms.

See [LICENSE](./LICENSE) for licensing information.

---

<p align="center">
  <strong>Ptero Panel PHPOnly</strong>
  <br>
  <em>Pure PHP. Full control. Pterodactyl compatibility.</em>
</p>
