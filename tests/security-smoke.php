<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$config = file_get_contents($root . '/config.php') ?: '';
$setup = file_get_contents($root . '/setup.php') ?: '';
$backup = file_get_contents($root . '/admin/backup.php') ?: '';
$mirrorDetail = file_get_contents($root . '/mirror.php') ?: '';
$searchPage = file_get_contents($root . '/search.php') ?: '';
$homePage = file_get_contents($root . '/index.php') ?: '';
$categoryPage = file_get_contents($root . '/category.php') ?: '';
$monitoring = file_get_contents($root . '/includes/monitoring.php') ?: '';
$repoHealth = file_get_contents($root . '/includes/repository-health.php') ?: '';
$ignore = file_get_contents($root . '/.gitignore') ?: '';
$htaccess = file_get_contents($root . '/.htaccess') ?: '';
$apiRouter = file_get_contents($root . '/api.php') ?: '';
$apiV1 = file_get_contents($root . '/includes/api-v1.php') ?: '';
$apiDocs = file_get_contents($root . '/docs/api-v1.md') ?: '';
$apiExample = file_get_contents($root . '/examples/api-v1.sh') ?: '';
$apiSpec = json_decode(file_get_contents($root . '/docs/openapi-v1.json') ?: '', true);
$installation = file_get_contents($root . '/includes/installation.php') ?: '';
$assert(is_file($root . '/setup.php'), 'New browser setup wizard is missing.');
$assert(!is_file($root . '/install.php'), 'Legacy install.php must be removed.');
$assert(str_contains($installation, 'function mirrorhubInstallationStatus'), 'Installation-state detector is missing.');
$assert(str_contains($installation, "'incomplete'"), 'Incomplete installation state is missing.');
$assert(str_contains($homePage, 'mirrorhubInstallationStatus'), 'Home page must detect setup state before loading database code.');
$assert(str_contains($setup, 'CREATE DATABASE IF NOT EXISTS'), 'Setup must attempt to create the target database when permitted.');
$assert(str_contains($setup, 'mirrorhub_installation'), 'Setup must persist an idempotent installation state.');
$assert(str_contains($setup, 'ensureMonitorSchema') && str_contains($setup, 'ensureCmsSchema'), 'Setup must create monitoring and CMS schemas.');
$assert(str_contains($setup, 'mirrorhubSetupWriteConfig'), 'Setup must persist local database configuration.');
$assert(str_contains($setup, 'password_hash($adminPassword'), 'Setup must hash the administrator password.');
$assert(str_contains($setup, 'session_regenerate_id(true)'), 'Setup must regenerate the session ID before admin login.');
$assert(str_contains($setup, '@unlink(__FILE__)'), 'Setup should remove its public installer after success.');
$assert(str_contains($htaccess, '.mirrorhub-installed'), 'Apache must deny direct reads of the installation lock.');
$assert(str_contains($config, 'MIRRORHUB_DB_PORT'), 'Database port must be configurable through the environment.');

$assert(!str_contains($config, "define('DB_NAME', '"), 'DB_NAME must not be hard-coded in config.php.');
$assert(!str_contains($config, "define('DB_USER', '"), 'DB_USER must not be hard-coded in config.php.');
$assert(!str_contains($config, "define('DB_PASS', '"), 'DB_PASS must not be hard-coded in config.php.');
$assert(str_contains($config, 'MIRRORHUB_DB_PASS'), 'Database password must be configurable through the environment.');
$assert(str_contains($config, 'config.local.php'), 'Local config file support is missing.');
$assert(!str_contains($setup, 'admin123'), 'Setup must not use a default admin password.');
$assert(str_contains($setup, 'setup_csrf_token'), 'Installer CSRF token is missing.');
$assert(str_contains($setup, 'password_hash($adminPassword'), 'Installer must hash the chosen admin password.');
$assert(str_contains($setup, 'minlength="12"'), 'Installer must enforce a strong minimum password length.');
$assert(str_contains($setup, 'pattern="[A-Za-z0-9_.-]{3,50}"'), 'Installer username constraints are missing.');
$assert(str_contains($setup, 'mb_strlen($adminPassword, \'UTF-8\') < 12'), 'Installer must check password length by characters.');
$assert(str_contains($setup, 'request_links'), 'Fresh installations must create request_links.');
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
$assert(str_contains($mirrorDetail, 'repo-config-data'), 'Mirror detail page must provide repository configuration templates.');
$assert(str_contains($mirrorDetail, 'mirror_checks'), 'Mirror detail page must show recent HTTP checks.');
$assert(str_contains($mirrorDetail, 'gpgcheck=1'), 'RPM template must keep signature checking enabled.');
$assert(str_contains($searchPage, 'mirror_repository_health'), 'Search filters must include repository metadata health.');
$assert(str_contains($searchPage, 'filterCategoryId'), 'Search filters must support categories.');
$assert(str_contains($searchPage, 'mirror.php?slug='), 'Search results must link to mirror detail pages.');
$assert(str_contains($homePage, 'mirror.php?slug='), 'Home mirror cards must link to mirror detail pages.');
$assert(str_contains($categoryPage, 'mirror.php?slug='), 'Category mirror cards must link to mirror detail pages.');
$assert(str_contains($htaccess, 'api/v1/mirrors'), 'Versioned API routes are missing.');
$assert(str_contains($htaccess, 'api/v1/contributions'), 'API contribution route is missing.');
$assert(str_contains($apiRouter, "api_version") && str_contains($apiRouter, "includes/api-v1.php"), 'API v1 dispatcher is missing.');
$assert(str_contains($apiV1, 'function mirrorhubApiV1'), 'API v1 router is missing.');
$assert(str_contains($apiV1, 'function mhApiValidateContribution'), 'Contribution validation is missing.');
$assert(str_contains($apiV1, "hasTable(\$pdo,'mirror_protocols')"), 'API protocol filtering must support legacy databases without multi-protocol tables.');
$assert(strpos($apiV1, 'mhApiQuota($pdo)') < strpos($apiV1, 'mhApiValidateContribution($in)'), 'API rate limiting must count invalid submission attempts.');
$assert(str_contains($apiV1, 'hash_hmac') && str_contains($apiV1, 'CREATE TABLE IF NOT EXISTS api_rate_limits'), 'Submission rate limit is missing.');
$assert(str_contains($apiV1, "'pending'"), 'API contributions must require moderation.');
$assert(str_contains($apiV1, 'min(100,$n)'), 'API page size cap is missing.');
$assert(is_array($apiSpec) && ($apiSpec['openapi'] ?? '') === '3.1.0', 'OpenAPI spec is invalid.');
$assert(isset($apiSpec['paths']['/mirrors'], $apiSpec['paths']['/contributions']), 'OpenAPI endpoints are incomplete.');
$assert(str_contains($apiDocs, '۸ تلاش') && str_contains($apiExample, 'API_BASE/status'), 'API docs or examples are missing.');

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

require_once $root . '/includes/api-v1.php';
$validApiSuggestion = mhApiValidateContribution([
    'name_fa' => 'میرور نمونه', 'urls' => ['https://mirror.example.invalid/repo', 'https://mirror.example.invalid/repo'],
    'protocols' => ['https'], 'phone' => '+989121234567', 'website' => '',
]);
$assert($validApiSuggestion['valid'] && count($validApiSuggestion['data']['urls']) === 1, 'API contribution must deduplicate valid URLs.');
$invalidApiSuggestion = mhApiValidateContribution(['name_fa' => 'bad', 'urls' => ['file:///etc/passwd'], 'protocols' => ['invalid'], 'phone' => '1']);
$assert(!$invalidApiSuggestion['valid'], 'API contribution must reject unsafe schemes, bad protocols and bad phone numbers.');
$apiPageCap = mhApiPage(['page' => '9999999', 'per_page' => '9999']);
$assert($apiPageCap['page'] === 10000 && $apiPageCap['per_page'] === 100, 'API pagination caps are incorrect.');

if ($failures) {
    fwrite(STDERR, "Smoke tests failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}
echo "Security and regression smoke tests passed.\n";
