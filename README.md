# MirrorHub

[![Quality Checks](https://github.com/ahb1390/MirrorHub/actions/workflows/quality.yml/badge.svg?branch=main)](https://github.com/ahb1390/MirrorHub/actions/workflows/quality.yml)
[![API](https://img.shields.io/badge/API-v1-236b46)](docs/api-v1.md)
[فارسی](README.fa.md)

MirrorHub is a PHP/MySQL web application for discovering software mirrors, organizing repositories and providers, collecting community suggestions, and monitoring provider availability and repository metadata freshness.

> **Project status:** actively evolving. Automated syntax and smoke checks pass, but deployments should still run installation, database, HTTP routing, backup/restore, and API integration tests in a staging environment.

## Features

- Searchable mirror directory with categories, protocols, filters, and dedicated mirror detail pages.
- Separate HTTP availability monitoring and repository-metadata freshness checks.
- Monitoring history, daily uptime, incidents, dashboard, and optional notifications.
- Community-submitted mirror suggestions, held for administrator review rather than published automatically.
- Public, versioned REST-style API v1 with pagination, filters, stable JSON envelopes, and OpenAPI documentation.
- Markdown-based guides/CMS, SEO metadata, sitemap and robots endpoints.
- Database backup/restore, CSRF protection in installation and administrative workflows, and GitHub Actions quality checks.

## Screens and API

- Website home: configured by the deployment's SITE_URL.
- Public status page: /status.php
- Search: /search.php
- Mirror details: /mirror.php?slug=SLUG
- Public API guide: [docs/api-v1.md](docs/api-v1.md)
- Machine-readable API contract: [docs/openapi-v1.json](docs/openapi-v1.json)
- Monitoring guide: [docs/monitoring.md](docs/monitoring.md)
- Installation and deployment: [docs/installation.fa.md](docs/installation.fa.md)
- Security and deployment hardening: [docs/security-deployment.md](docs/security-deployment.md)

## Technology

- PHP 8.2 is the version currently exercised by CI.
- MySQL/MariaDB through PDO.
- Apache-compatible URL rewriting via .htaccess for clean routes.
- Browser-side JavaScript; no Node build step is required for the current asset pipeline.
- No Composer dependency installation is currently required by the application.

Recommended PHP extensions: pdo_mysql, mbstring, fileinfo and curl. TLS certificate validation is expected to remain enabled for monitoring.

## Quick installation

See the detailed Persian guide in [docs/installation.fa.md](docs/installation.fa.md). In brief:

1. Provide a PHP 8.2+ web environment, MySQL/MariaDB database, and Apache rewrite support.
2. Put the repository files under the intended web root.
3. Open the site root; a fresh deployment redirects to /setup.php automatically.
4. Enter the MySQL/MariaDB connection details, the public site URL (including any subdirectory), and a new administrator account with a password of at least 12 characters.
5. Setup tests the connection, prepares the application schema, saves the private configuration, locks completed installation, and signs the administrator in. On hosts that permit it, setup.php removes itself after completion.
6. Configure the provider categories, add mirrors, select repository-health profiles where applicable, and configure a real server cron for monitoring.

Do not commit config.local.php, real credentials, bot tokens, backup exports, or production logs.

## API quick start

Set the base URL to the installation's public API base. For the sample beta installation it is:

~~~sh
API_BASE="https://mirrorhub.ir/beta/api/v1"
curl --fail --silent --show-error "$API_BASE/mirrors?per_page=10"
curl --fail --silent --show-error "$API_BASE/categories?type=software"
curl --fail --silent --show-error "$API_BASE/status"
curl --fail --silent --show-error "$API_BASE/openapi.json"
~~~

Available resources include mirrors, mirror details, categories, public stats, provider status, recent check history, and moderated contributions. Public reads do not require an API key. The contribution endpoint creates a pending request; it does not publish or activate a mirror. Consult the [API contract](docs/api-v1.md) for parameters, response format, limits, and caveats.

The old api.php?action=... endpoints are retained for compatibility while v1 is introduced.

## Monitoring and data integrity

HTTP status and repository metadata health are intentionally separate. A reachable URL does not prove that packages are current, that all repository files exist, or that GPG signatures are valid. Current metadata profiles do not perform full cryptographic signature verification or verify every package hash. Use the package manager's normal signature validation when installing software.

See [docs/monitoring.md](docs/monitoring.md) for cron setup, provider monitoring, metadata profiles, and notification configuration.

## Development and checks

The repository's GitHub Actions workflow currently runs PHP lint, PHP security/regression smoke checks, and JavaScript syntax checks.

~~~sh
php -l api.php
php -l includes/api-v1.php
php tests/security-smoke.php
node --check assets/js/app.js
~~~

The full workflow checks every PHP file and JavaScript file under assets/js. These checks do not replace integration tests with a real MySQL/MariaDB instance or browser-based deployment tests.

Contribution guidelines: [CONTRIBUTING.md](CONTRIBUTING.md). To report a security issue, read [SECURITY.md](SECURITY.md).

## License

This project is licensed under the **GNU General Public License v3.0 only (GPL-3.0-only)**. See [LICENSE](LICENSE) for the full license text. Unless explicitly stated otherwise, this license applies to the project files for which the project maintainers hold the necessary rights.
