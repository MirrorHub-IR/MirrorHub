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
$assert(str_contains($installer, 'request_links'), 'Fresh installations must create request_links.');
$assert(str_contains($ignore, '/config.local.php'), 'Local config must be git-ignored.');
$assert(str_contains($htaccess, 'config\.local\.php'), 'Apache must deny direct access to the local config file.');

foreach (['request_links', 'mirror_checks', 'mirror_status', 'mirror_daily_stats', 'mirror_incidents', 'monitor_settings'] as $table) {
    $assert(str_contains($backup, "'" . $table . "'"), 'Backup coverage missing table: ' . $table);
}
$assert(str_contains($backup, 'MIRRORHUB_BACKUP_VERSION = 2'), 'Backup format version was not incremented.');
$assert(str_contains($backup, 'legacyMissingTables'), 'Legacy backup safety guard is missing.');

require_once $root . '/includes/functions.php';
require_once $root . '/includes/cms.php';

$protocols = normalizeProtocols(['https', 'ftp', 'invalid'], []);
$assert(count($protocols) === 2, 'Protocol normalizer smoke test failed.');

$escaped = cmsInline('<script>alert(1)</script>');
$assert(str_contains($escaped, '&lt;script&gt;'), 'CMS inline renderer must escape raw HTML.');

if ($failures) {
    fwrite(STDERR, "Smoke tests failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}
echo "Security and regression smoke tests passed.\n";
