<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$config = file_get_contents($root . '/config.php') ?: '';
$installer = file_get_contents($root . '/install.php') ?: '';
$backup = file_get_contents($root . '/admin/backup.php') ?: '';
$monitoring = file_get_contents($root . '/includes/monitoring.php') ?: '';
$repoHealth = file_get_contents($root . '/includes/repository-health.php') ?: '';
$ignore = file_get_contents($root . '/.gitignore') ?: '';
$htaccess = file_get_contents($root . '/.htaccess') ?: '';

$assert(!str_contains($config, "define('DB_NAME', '"), 'DB_NAME must not be hard-coded in config.php.');
$assert(!str_contains($config, "define('DB_USER', '"), 'DB_USER must not be hard-coded in config.php.');
$assert(!str_contains($config, "define('DB_PASS', '"), 'DB_PASS must not be hard-coded in config.php.');
$assert(str_contains($config, 'MIRRORHUB_DB_PASS'), 'Database password must be configurable through the environment.');
$assert(str_contains($config, 'config.local.php'), 'Local config file support is missing.');
$assert(!str_contains($installer, 'admin123'), 'Installer must not use a default admin password.');
$assert(str_contains($installer, 'install_csrf_token'), 'Installer CSRF token is missing.');
$assert(str_contains($installer, 'password_hash($adminPassword'), 'Installer must hash the chosen admin password.');
$assert(str_contains($installer, 'minlength="12"'), 'Installer must enforce a strong minimum password length.');
$assert(str_contains($installer, 'pattern="[A-Za-z0-9_.-]{3,50}"'), 'Installer username constraints are missing.');
$assert(str_contains($installer, 'mb_strlen($adminPassword, \'UTF-8\') < 12'), 'Installer must check password length by characters.');
$assert(str_contains($installer, 'request_links'), 'Fresh installations must create request_links.');
$functions = file_get_contents($root . '/includes/functions.php') ?: '';
$categoryAdmin = file_get_contents($root . '/admin/categories.php') ?: '';
$assert(!str_contains($functions, 'image/svg+xml'), 'Category icon upload must not accept SVG.');
$assert(str_contains($functions, 'getimagesize'), 'Uploaded category icons must be checked as actual raster images.');
$assert(str_contains($categoryAdmin, 'image/webp'), 'Category icon form must advertise WebP uploads.');
$assert(str_contains($ignore, '/config.local.php'), 'Local config must be git-ignored.');
$assert(str_contains($htaccess, 'config\.local\.php'), 'Apache must deny direct access to the local config file.');

foreach (['request_links', 'mirror_checks', 'mirror_status', 'mirror_daily_stats', 'mirror_incidents', 'monitor_settings', 'mirror_repository_health'] as $table) {
    $assert(str_contains($backup, "'" . $table . "'"), 'Backup coverage missing table: ' . $table);
}
$assert(str_contains($backup, 'MIRRORHUB_BACKUP_VERSION = 3'), 'Backup format version was not incremented.');
$assert(str_contains($backup, 'legacyMissingTables'), 'Legacy backup safety guard is missing.');
$assert(str_contains($monitoring, 'mirror_repository_health'), 'Repository-health schema is missing.');
$assert(str_contains($repoHealth, 'function monitorEvaluateRepositoryHealth'), 'Repository-health evaluator is missing.');

require_once $root . '/includes/functions.php';
require_once $root . '/includes/cms.php';
require_once $root . '/includes/monitoring.php';

$badPath = monitorNormalizeRepositoryPath('custom', '../private');
$assert(!$badPath['valid'], 'Repository metadata path traversal must be rejected.');
$defaultPath = monitorNormalizeRepositoryPath('alpine_index', '');
$assert($defaultPath['valid'] && $defaultPath['path'] === 'latest-stable/main/x86_64/APKINDEX.tar.gz', 'Alpine default metadata path is wrong.');

$now = strtotime('2026-01-10 12:00:00 UTC');
$healthyInRelease = monitorEvaluateRepositoryHealth('debian_release', [
    'http_code' => 200,
    'response_ms' => 30,
    'body' => "Origin: Debian\nDate: Sat, 10 Jan 2026 10:00:00 UTC\nValid-Until: Sun, 11 Jan 2026 10:00:00 UTC\n",
], 168, $now);
$assert($healthyInRelease['status'] === 'healthy', 'Fresh InRelease metadata should be healthy.');

$expiredInRelease = monitorEvaluateRepositoryHealth('debian_release', [
    'http_code' => 200,
    'body' => "Date: Sat, 03 Jan 2026 10:00:00 UTC\nValid-Until: Sun, 04 Jan 2026 10:00:00 UTC\n",
], 168, $now);
$assert($expiredInRelease['status'] === 'stale', 'Expired Valid-Until metadata should be stale.');

$missingMetadata = monitorEvaluateRepositoryHealth('arch_db', ['http_code' => 404], 168, $now);
$assert($missingMetadata['status'] === 'invalid', 'Missing metadata should be invalid, not an HTTP uptime outage.');

$protocols = normalizeProtocols(['https', 'ftp', 'invalid'], []);
$assert(count($protocols) === 2, 'Protocol normalizer smoke test failed.');

$escaped = cmsInline('<script>alert(1)</script>');
$assert(str_contains($escaped, '&lt;script&gt;'), 'CMS inline renderer must escape raw HTML.');

if ($failures) {
    fwrite(STDERR, "Smoke tests failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}
echo "Security and regression smoke tests passed.\n";
