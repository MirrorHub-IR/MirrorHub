<p align="center">
  <img src="assets/logo.png" alt="MirrorHub logo" width="112">
</p>
<h1 align="center">MirrorHub</h1>
<p align="center"><strong>Discover, organize, and monitor software mirrors.</strong><br>
A self-hosted mirror directory with provider monitoring, repository metadata checks, a public API, and a Markdown knowledge base.</p>
<p align="center">
  <a href="https://github.com/MirrorHub-IR/MirrorHub/actions/workflows/quality.yml"><img src="https://github.com/MirrorHub-IR/MirrorHub/actions/workflows/quality.yml/badge.svg?branch=main" alt="Quality Checks"></a>
  <a href="https://github.com/MirrorHub-IR/MirrorHub/actions/workflows/semgrep.yml"><img src="https://github.com/MirrorHub-IR/MirrorHub/actions/workflows/semgrep.yml/badge.svg?branch=main" alt="Semgrep Security"></a>
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.2+">
  <a href="https://github.com/MirrorHub-IR/MirrorHub/blob/main/LICENSE"><img src="https://img.shields.io/github/license/MirrorHub-IR/MirrorHub" alt="License"></a>
  <a href="https://github.com/MirrorHub-IR/MirrorHub/stargazers"><img src="https://img.shields.io/github/stars/MirrorHub-IR/MirrorHub?style=flat" alt="GitHub stars"></a>
  <a href="https://github.com/MirrorHub-IR/MirrorHub/network/members"><img src="https://img.shields.io/github/forks/MirrorHub-IR/MirrorHub?style=flat" alt="GitHub forks"></a>
</p>
<p align="center"><a href="README.fa.md">نسخهٔ فارسی</a> · <strong>English</strong></p>

---

## Overview

MirrorHub is an open-source, self-hosted PHP and MySQL/MariaDB application for discovering software mirrors and monitoring the infrastructure behind them. It combines a searchable public catalogue with provider administration, HTTP availability checks, repository-metadata freshness checks, community-submitted suggestions, and a versioned REST-style API.

The application is designed to run on a conventional PHP web host. It does not currently require Composer or an npm build pipeline.

> **Project maturity:** MirrorHub is under active development and is currently pre-1.0. The repository has automated quality checks, security smoke tests, and a Semgrep security-scanning workflow. Automated checks are not a substitute for staging, database integration, browser, backup-restore, and production security testing. The initial release notes are in [docs/releases/v0.1.0.md](docs/releases/v0.1.0.md); they do not imply that a GitHub Release has already been published.

## Features

- **Mirror discovery:** searchable catalogue, category pages, provider pages, mirror detail pages, pagination, and filters for category, protocol, HTTP status, and repository-metadata health.
- **Provider organization:** software and provider categories are distinct. Provider Base URLs can scope provider pages, administration filters, and status views to the matching URL subtree.
- **HTTP monitoring:** scheduled HTTP/HTTPS checks for active provider mirrors, retry handling, configurable timeouts and thresholds, response-time history, daily uptime, incidents, and a public status page.
- **Repository metadata health:** optional profiles for Debian/Ubuntu, Alpine, Arch Linux, RPM metadata, and a custom relative metadata path.
- **Repository setup helpers:** copyable APT, APK, Pacman, and generic RPM configuration templates on mirror detail pages.
- **Community contributions:** visitors can submit mirror suggestions. Submissions are validated, rate-limited, and held for administrator moderation; they never activate a mirror automatically.
- **Public API v1:** paginated mirror discovery, details, categories, statistics, provider status, check history, and moderated contribution intake.
- **OpenAPI:** machine-readable OpenAPI 3.1 documentation and shell examples for API consumers.
- **Markdown CMS:** administrator-managed guides with drafts, publishing, SEO fields, category association, and a live mirror-health block.
- **Administration:** browser-based setup, administrator login, category/provider management, mirror management, request moderation, user administration, monitoring settings, and database backup/restore.
- **Backup and restore:** JSON and ZIP formats. ZIP support requires PHP ZipArchive; JSON remains available without it. Upload-file entries are checked for safe paths, size, and SHA-256 integrity during backup/restore.
- **Responsive interface:** Persian right-to-left and English-friendly content, locally hosted Vazirmatn font, responsive public pages and admin screens, and browser-side HTTP ping timing.
- **Community information:** configurable Donate/contact page. It is a project-information and contact page, not a payment gateway.

## Screens and routes

The exact base URL depends on the deployment's SITE_URL setting.

| Area | Route |
| --- | --- |
| Home | / |
| All mirrors | /mirrors/ |
| Categories | /categories/ |
| Providers | /providers/ |
| Public status | /status/ or /status.php |
| Search | /search.php |
| Mirror details | /mirror.php?slug=SLUG |
| Guides | /guides/ and /guides/SLUG |
| Donate / contact | /donate.php |
| API v1 | /api/v1/ |
| API OpenAPI document | /api/v1/openapi.json |
| First-run setup | /setup.php |

Clean routes depend on Apache mod_rewrite and the repository's .htaccess. Nginx and other web servers need equivalent rewrite and access-control rules configured by the operator.

## Technology and requirements

| Component | Requirement / baseline |
| --- | --- |
| Runtime | PHP 8.2 or newer; PHP 8.2 is the current CI baseline |
| Database | MySQL or MariaDB |
| Required PHP extensions | pdo_mysql, mbstring, fileinfo, curl |
| Web server | Apache with mod_rewrite and .htaccess support |
| Transport | HTTPS strongly recommended for public deployments |
| ZIP backup | ZipArchive (optional; JSON backup does not depend on it) |
| Development checks | PHP CLI; Node.js 22 for JavaScript syntax checks |
| Build dependencies | No Composer or npm install step is required by the current application |

For production, use a dedicated database and least-privilege database user. The installer may attempt to create the target database when the supplied account is allowed to do so; on shared hosting, create the database and user in the hosting panel first.

## Installation

The full English setup guide is [docs/installation.md](docs/installation.md). The Persian guide is [docs/installation.fa.md](docs/installation.fa.md).

1. Upload or clone the repository into the intended web root.
2. Create a MySQL/MariaDB database and a dedicated user, or use credentials that have the database privileges required by setup.
3. Open the site root. A fresh installation redirects to /setup.php.
4. Enter the database connection, the canonical site URL (including a subdirectory such as /beta, if applicable), and a new administrator account.
5. Use a unique administrator password of at least 12 characters.
6. Complete setup. The wizard prepares the application schema, writes private local configuration, records installation state, and signs the administrator in.
7. If the host cannot remove setup.php automatically, remove it from the deployed web root after confirming installation. Keep .mirrorhub-installed and config.local.php private.
8. Sign in at /admin/, configure software/provider categories, add mirrors, and review monitoring settings.
9. Configure a real server cron job for monitoring. Do not rely on traffic-triggered fallback execution as the production scheduler.

Do not commit config.local.php, database credentials, bot tokens, Cron keys, backup exports, uploaded/runtime content, or production logs.

### Configuration

Configuration is read from environment variables first and then from the server-only config.local.php file. Start from [config.local.example.php](config.local.example.php); never put production credentials into that example.

Recognized settings include:

- MIRRORHUB_DB_HOST
- MIRRORHUB_DB_PORT
- MIRRORHUB_DB_NAME
- MIRRORHUB_DB_USER
- MIRRORHUB_DB_PASS
- MIRRORHUB_DB_CHARSET
- MIRRORHUB_SITE_URL
- MIRRORHUB_ENVIRONMENT
- MIRRORHUB_MONITOR_CRON_KEY

Environment variables take precedence over values in config.local.php. The site URL must include the installation subdirectory when the app is not deployed at the domain root.

## Monitoring

MirrorHub deliberately separates two different measurements:

1. **HTTP availability** checks whether an active provider mirror responds over HTTP or HTTPS. It records response time, status, check history, uptime statistics, and incidents.
2. **Repository metadata health** checks a configured metadata file or its freshness signals. Available profiles include Debian InRelease, Ubuntu InRelease, Alpine APKINDEX.tar.gz, Arch core.db, RPM repodata/repomd.xml, and a custom relative path.

Only active mirrors assigned to at least one provider category are included in the scheduled provider-monitoring cycle. The actual URL scheme determines whether an endpoint is HTTP or HTTPS; the protocol metadata field alone is not authoritative.

**Important security limitation:** a successful HTTP request or a fresh metadata timestamp does not prove that a repository is complete, that every package hash is correct, or that GPG signatures are cryptographically valid. MirrorHub's current repository-health checks do not perform full signature verification. Keep the distribution's normal package-manager signature and hash verification enabled.

For server scheduling, use the cron command generated by the admin monitoring page. See [docs/monitoring.md](docs/monitoring.md).

## Public API v1

The versioned API is mounted under /api/v1/. Public read endpoints do not require an API key.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | /mirrors | Search and paginate mirrors |
| GET | /mirrors/{slug} | Read an active mirror |
| GET | /categories | List categories |
| GET | /stats | Read public site statistics |
| GET | /status | Read provider status |
| GET | /status/{slug}/history | Read recent checks and uptime history |
| POST | /contributions | Submit a mirror suggestion for moderation |
| GET | /openapi.json | Retrieve the OpenAPI 3.1 document |

Example requests (replace the base URL with the value for your deployment):

~~~sh
API_BASE="https://mirrorhub.ir/beta/api/v1"
curl --fail --silent --show-error "$API_BASE/mirrors?per_page=10"
curl --fail --silent --show-error "$API_BASE/mirrors?q=debian&protocol=https"
curl --fail --silent --show-error "$API_BASE/categories?type=software"
curl --fail --silent --show-error "$API_BASE/status"
curl --fail --silent --show-error "$API_BASE/openapi.json"
~~~

The mirrorhub.ir/beta base URL is the example installation referenced by the current documentation; it is not a guarantee that a hosted demo or public service is available.

The contribution endpoint creates a pending request and does not publish or enable a mirror. The current API documentation specifies a limit of eight contribution attempts per hour per client IP and a maximum of ten URLs per submission. See [docs/api-v1.md](docs/api-v1.md) and [examples/api-v1.sh](examples/api-v1.sh) for the full contract and examples.

## Backup and restore

The admin backup tool supports JSON and ZIP archives. The current backup format is version 6. ZIP requires ZipArchive; JSON is the fallback when ZIP support is unavailable.

For uploaded files, the implementation applies limits and validation during backup/restore, including relative-path checks, file/count/total-size limits, rejection of symlinks and server-executable files, and SHA-256 checks. Backup archives can contain private database and notification settings, so store them encrypted or in a restricted location. A successful archive download is not proof that restore will succeed: test restoration in a separate staging environment before relying on a backup.

See the backup section of [docs/admin-guide.md](docs/admin-guide.md) (Persian) for the detailed limits and restore behavior.

## Markdown guides and content management

The integrated CMS stores articles in the application database. Administrators can create drafts, publish/edit/delete guides, set SEO metadata, and associate content with software or provider categories. Published articles are available under /guides/.

A guide can render stored monitoring data for a software category by placing a directive on its own line:

~~~text
:::health ubuntu
~~~

Replace ubuntu with the slug of an existing software category. This displays current stored mirror-health information; it does not run a new network check while rendering the article. See [docs/markdown-cms.md](docs/markdown-cms.md) (Persian).

## Browser ping

The Ping control measures browser-side HTTP request round-trip time. It is not ICMP ping and is not a server-side availability check. Browser policy, mixed-content blocking, DNS, TLS, network conditions, and extensions may affect the result. Treat it as a convenience measurement, not authoritative provider health.

## Security model and responsible disclosure

Security-related controls in the current codebase include:

- PDO-based database access and prepared statements for request data.
- CSRF tokens for state-changing browser workflows.
- Password hashing and session-ID regeneration during administrator setup/login.
- Strict session settings, including HTTP-only and SameSite cookies.
- Environment/local-file configuration for database credentials and Cron secrets.
- Server-side input validation and context-aware output escaping.
- Rate-limited, HMAC-keyed contribution throttling and administrator moderation.
- SVG sanitization for uploaded category icons.
- Backup path, file-type, size, and integrity checks.
- Apache access restrictions for sensitive local files and installation markers.

This is a description of implemented safeguards, not a guarantee that the application is vulnerability-free. Operators must keep PHP, the database, and the web server patched; enforce HTTPS; protect backups and secrets; restrict upload permissions; and test the installation in staging.

Please read [SECURITY.md](SECURITY.md) before reporting a vulnerability. Do not post exploit details, private user data, or credentials in public issues.

## Repository layout

~~~text
.
├── .github/                GitHub Actions, issue forms, and contribution templates
├── admin/                  Administrator pages
├── assets/                 CSS, JavaScript, logo, and local fonts
├── cron/                   Cron examples for Unix-like systems and Windows
├── docs/                   Developer, API, installation, monitoring, and release docs
├── examples/               API usage examples
├── includes/               Database, CMS, API, monitoring, and shared functions
├── migrations/             SQL schema migrations
├── tests/                  Security and regression smoke tests
├── api.php                 Legacy API compatibility entry point
├── setup.php               Browser-based first-run installer
└── ...
~~~

## Development

Clone the repository and run the checks relevant to your changes. The application does not currently require Composer or npm dependencies for local development.

~~~sh
git clone https://github.com/MirrorHub-IR/MirrorHub.git
cd MirrorHub
php -l path/to/file.php
php tests/security-smoke.php
node --check assets/js/app.js
~~~

Run a complete syntax pass from the repository root:

~~~sh
find . -type f -name '*.php' -not -path './.git/*' -print0 | while IFS= read -r -d '' file; do
  php -l "$file" || exit 1
done
find assets/js -type f -name '*.js' -print0 | while IFS= read -r -d '' file; do
  node --check "$file" || exit 1
done
~~~

The GitHub Actions quality workflow runs PHP lint, the security/regression smoke suite, and JavaScript syntax checks. The Semgrep workflow scans the repository with community security rules and uploads SARIF findings to GitHub code scanning where supported. Dependabot checks GitHub Actions dependencies weekly. These workflows do not currently provision a live MySQL/MariaDB integration database or execute browser-based end-to-end tests.

### GitHub contribution workflow

1. Search existing issues and pull requests before starting duplicate work.
2. Fork the repository and create a focused branch.
3. Keep each change reviewable and include tests or explain why a test cannot be added.
4. Update the relevant docs, migration path, backup coverage, and compatibility notes.
5. Open a pull request using the repository template.

Read [CONTRIBUTING.md](CONTRIBUTING.md), [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md), and [SUPPORT.md](SUPPORT.md).

## Documentation index

| Document | Language | Description |
| --- | --- | --- |
| [Installation](docs/installation.md) | English | Browser setup and server prerequisites |
| [Installation](docs/installation.fa.md) | فارسی | نصب و راه‌اندازی |
| [API v1](docs/api-v1.md) | English | REST endpoints, filters, responses, and limits |
| [OpenAPI 3.1](docs/openapi-v1.json) | JSON | Machine-readable API schema |
| [Admin guide](docs/admin-guide.md) | فارسی | Categories, mirrors, CMS, monitoring, backups |
| [Monitoring](docs/monitoring.md) | فارسی | Cron, provider checks, repository metadata |
| [Markdown CMS](docs/markdown-cms.md) | فارسی | Guide authoring and health blocks |
| [Security and deployment](docs/security-deployment.md) | فارسی | Deployment hardening and secret handling |
| [Release notes](docs/releases/v0.1.0.md) | English / فارسی | Initial public preview notes |

## Project status and roadmap

MirrorHub is pre-1.0. The current repository includes the core application, setup wizard, API v1, monitoring, backups, and baseline automated checks. Before treating it as a mature production dependency, the maintainers should continue to prioritize:

- Automated integration tests against supported MySQL/MariaDB versions.
- Browser-level end-to-end tests for public and administrator workflows.
- A documented and repeatable backup/restore test process.
- Broader compatibility testing across supported PHP and web-server versions.
- Real-world adoption, operator feedback, issue triage, and tagged releases.

The project does not claim full package authenticity or cryptographic repository verification.

## License and third-party assets

The project is distributed under the **GNU General Public License v3.0 only (GPL-3.0-only)**. See [LICENSE](LICENSE).

The locally bundled Vazirmatn font has a separate license notice at [assets/fonts/OFL.txt](assets/fonts/OFL.txt) and is distributed under the SIL Open Font License 1.1. Respect the font's separate license terms when redistributing that asset.
