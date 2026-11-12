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
$donate = file_get_contents($root . '/includes/donate.php') ?: '';
$donatePage = file_get_contents($root . '/donate.php') ?: '';
$donateAdmin = file_get_contents($root . '/admin/donate.php') ?: '';
$layout = file_get_contents($root . '/includes/layout.php') ?: '';
$cms = file_get_contents($root . '/includes/cms.php') ?: '';
$cmsAdmin = file_get_contents($root . '/admin/pages.php') ?: '';
$docsPage = file_get_contents($root . '/docs.php') ?: '';
$sitemap = file_get_contents($root . '/sitemap.php') ?: '';
$robots = file_get_contents($root . '/robots.php') ?: '';
$mirrorsDirectory = file_get_contents($root . '/mirrors.php') ?: '';
$softwareDirectory = file_get_contents($root . '/categories.php') ?: '';
$providerDirectory = file_get_contents($root . '/providers.php') ?: '';
$contributePage = file_get_contents($root . '/contribute.php') ?: '';
$siteJs = file_get_contents($root . '/assets/js/site.js') ?: '';
$monitorAdmin = file_get_contents($root . '/admin/monitor.php') ?: '';
$backupAdmin = file_get_contents($root . '/admin/backup.php') ?: '';
$adminStyles = file_get_contents($root . '/assets/css/admin.css') ?: '';
$adminHelp = file_get_contents($root . '/admin/help.php') ?: '';
$adminGuide = file_get_contents($root . '/docs/admin-guide.md') ?: '';
$appJs = file_get_contents($root . '/assets/js/app.js') ?: '';

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
$assert(str_contains($donate, 'function ensureDonateSchema'), 'Donate settings schema helper is missing.');
$assert(str_contains($donate, 'function donateValidateUrl'), 'Donate URL validation helper is missing.');
$assert(str_contains($donate, "'bale_url' => ''"), 'Donate settings must include the configurable Bale URL.');
$assert(str_contains($donateAdmin, "'bale_url' => 'لینک بله'"), 'Donate admin must validate the Bale URL.');
$assert(str_contains($donatePage, "\$settings['bale_url']") && str_contains($donatePage, "'kind' => 'bale'") && str_contains($donatePage, 'donate-channel-'), 'Public Donate page must render the configured Bale contact card.');
$assert(str_contains($donateAdmin, 'verifyCsrfToken') && str_contains($donateAdmin, 'generateCsrfToken'), 'Donate settings must use CSRF protection.');
$assert(str_contains($donateAdmin, 'donateSaveSettings'), 'Donate settings must be persisted.');
$assert(str_contains($donatePage, 'htmlspecialchars') && str_contains($donatePage, 'rel="noopener noreferrer"'), 'Public contact links must be escaped and opened safely.');
$assert(str_contains($layout, '/admin/donate.php') && str_contains($layout, '/donate.php'), 'Donate links must be present in admin and public navigation.');
$assert(str_contains($backup, 'donate_settings') && str_contains($backup, 'MIRRORHUB_BACKUP_VERSION = 5'), 'Donate settings must be included in backups.');

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
$categoryFunctions = file_get_contents($root . '/includes/functions.php') ?: '';
$categoryPublic = file_get_contents($root . '/category.php') ?: '';
$contributePage = file_get_contents($root . '/contribute.php') ?: '';
$assert(str_contains($categoryFunctions, 'function sanitizeCategorySvg'), 'Safe SVG sanitizer is missing.');
$assert(str_contains($categoryFunctions, 'function ensureCategoryBaseUrlSchema'), 'Category base URL migration helper is missing.');
$assert(str_contains($categoryFunctions, 'function categoryBaseUrlIsValid'), 'Category base URL validation helper is missing.');
$assert(str_contains($categoryFunctions, 'function providerUrlMatchesBaseUrl'), 'Provider URL/base URL matching helper is missing.');
$assert(str_contains($categoryFunctions, 'getimagesize'), 'Uploaded raster category icons must be checked as actual images.');
$assert(str_contains($categoryAdmin, 'image/svg+xml') && str_contains($categoryAdmin, 'name="base_url"'), 'Category editor must accept SVG icons and expose a base URL.');
$assert(str_contains($categoryAdmin, 'delete-category-icon-form'), 'Category icon removal form must be separate from the edit form.');
$assert(!str_contains($categoryAdmin, '<form action="<?= SITE_URL ?>/admin/categories.php?id=<?= $id ?>" method="POST" style="display: inline-block;'), 'Category editor must not contain nested forms.');
$assert(str_contains($categoryPublic, "\$category['base_url']"), 'Public category page must display the configured base URL.');
$assert(str_contains($categoryPublic, 'providerUrlMatchesBaseUrl') && str_contains($categoryPublic, "['type'] ?? '') === 'provider'"), 'Provider-only category pages must filter HTTP(S) mirror URLs by provider Base URL.');
$assert(str_contains($cmsAdmin, "type IN ('software','provider')"), 'Guides must be attachable to both software and provider categories.');
$assert(str_contains($categoryPage, "WHERE category_id = ? AND status = 'published'"), 'Every category page must show its published guides.');
$assert(str_contains($categoryFunctions, "'code-2' =>"), 'Development SVG icon must exist in the icon registry.');
$assert(str_contains($contributePage, "icon('code-2', 24)"), 'Project development contribution card must display its icon.');
$assert(str_contains($ignore, '/config.local.php'), 'Local config must be git-ignored.');
$assert(str_contains($htaccess, 'config\.local\.php'), 'Apache must deny direct access to the local config file.');

foreach (['request_links', 'mirror_checks', 'mirror_status', 'mirror_daily_stats', 'mirror_incidents', 'monitor_settings', 'mirror_repository_health'] as $table) {
    $assert(str_contains($backup, "'" . $table . "'"), 'Backup coverage missing table: ' . $table);
}
$assert(str_contains($backup, 'MIRRORHUB_BACKUP_VERSION = 6'), 'Backup format version must include JSON/ZIP file handling.');
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
$assert(providerUrlMatchesBaseUrl('https://mirror.example.invalid/ubuntu/dists/stable', 'https://mirror.example.invalid/ubuntu'), 'Provider Base URL must match the same HTTPS host and path subtree.');
$assert(!providerUrlMatchesBaseUrl('https://mirror.example.invalid/ubuntu-extra', 'https://mirror.example.invalid/ubuntu'), 'Provider Base URL path matching must enforce a path boundary.');
$assert(!providerUrlMatchesBaseUrl('http://mirror.example.invalid/ubuntu', 'https://mirror.example.invalid/ubuntu'), 'Provider Base URL filtering must not mix HTTP and HTTPS.');
$assert(!providerUrlMatchesBaseUrl('rsync://mirror.example.invalid/ubuntu', 'https://mirror.example.invalid/ubuntu'), 'Provider Base URL filtering must reject non-HTTP(S) schemes.');
require_once $root . '/includes/monitoring.php';
require_once $root . '/includes/donate.php';
$assert(monitorUrlScheme('https://mirror.example/repo') === 'https', 'Monitor URL scheme must detect HTTPS from the URL.');
$assert(monitorUrlScheme('http://mirror.example/repo') === 'http', 'Monitor URL scheme must detect HTTP from the URL.');
$assert(monitorUrlScheme('ftp://mirror.example/repo') === '', 'Monitor URL scheme must exclude non-HTTP protocols.');
$assert(donateValidateUrl('https://t.me/mirrorhub'), 'Valid HTTPS Telegram links should be accepted.');
$assert(donateValidateUrl('https://ble.ir/mirrorhub'), 'Valid HTTPS Bale links should be accepted.');
$assert(donateValidateUrl('mailto:hello@example.com'), 'Valid mailto contact links should be accepted.');
$assert(!donateValidateUrl('javascript:alert(1)'), 'JavaScript URLs must be rejected for Donate links.');
$assert(!donateValidateUrl('http://example.com'), 'Plain HTTP links must be rejected for Donate links.');
$assert(!donateValidateUrl('javascript:alert(1)'), 'Unsafe schemes must also be rejected for the Bale URL.');
$assert(!donateValidateUrl('https://user:pass@example.com'), 'Credential-bearing URLs must be rejected for Donate links.');
if (class_exists(DOMDocument::class)) {
    $unsafeSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" onload="alert(1)"><script>alert(1)</script><path d="M1 1h2" fill="url(https://evil.example/a.svg)"/><circle cx="5" cy="5" r="2"/></svg>';
    $safeSvg = sanitizeCategorySvg($unsafeSvg);
    $assert(is_string($safeSvg) && !str_contains($safeSvg, '<script') && !str_contains($safeSvg, 'onload=') && !str_contains($safeSvg, 'https://evil.example'), 'SVG upload sanitizer must remove active and external content.');
    $assert(sanitizeCategorySvg('<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg>&x;</svg>') === false, 'SVG upload sanitizer must reject DTD/entity input.');
}
$assert(categoryBaseUrlIsValid('https://mirror.example.org/repo'), 'HTTPS category base URLs must be accepted.');
$assert(categoryBaseUrlIsValid('http://mirror.example.org/repo'), 'HTTP category base URLs must be accepted.');
$assert(categoryBaseUrlIsValid(''), 'Category base URL may be empty.');
$assert(!categoryBaseUrlIsValid('ftp://mirror.example.org/repo'), 'Category base URLs must only allow HTTP or HTTPS.');
$assert(!categoryBaseUrlIsValid('https://user:pass@mirror.example.org'), 'Category base URLs must reject embedded credentials.');



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
$assert(str_contains($cms, 'function cmsDeleteArticle'), 'CMS deletion helper is missing.');
$assert(str_contains($cmsAdmin, 'name="article_id"'), 'CMS delete form must submit the article id in POST.');
$assert(str_contains($cmsAdmin, 'verifyCsrfToken') && str_contains($cmsAdmin, 'cmsDeleteArticle'), 'CMS deletion must remain CSRF-protected and use the deletion helper.');
preg_match_all("/\\bicon\\('([^']+)'/", $homePage, $homepageIconMatches);
foreach (array_unique($homepageIconMatches[1] ?? []) as $homepageIconName) {
    $assert(str_contains($functions, "'" . $homepageIconName . "' =>"), 'Homepage icon is missing from the SVG registry: ' . $homepageIconName);
}
$assert(str_contains($cmsAdmin, 'name="req_action" value="delete"'), 'CMS delete form must send an explicit delete action independently of its submit button.');
$assert(str_contains($appJs, 'form.requestSubmit(submitter)'), 'Confirmed forms must preserve the clicked submitter when they are re-submitted.');
$assert(str_contains($appJs, 'submitter.name') && str_contains($appJs, 'submitter.value'), 'Older-browser form submission fallback must preserve submitter data.');
$assert(str_contains($docsPage, "'article_schema'"), 'Public guides must publish Article structured data.');
$assert(str_contains($layout, 'twitter:description') && str_contains($layout, 'application/ld+json'), 'Global social metadata or JSON-LD output is missing.');
$assert(str_contains($sitemap, 'MAX(updated_at)') && str_contains($sitemap, '<lastmod>'), 'Guide sitemap must expose accurate last-modified dates.');
$assert(str_contains($robots, '/setup.php'), 'Robots rules must keep the installer out of crawling.');
$assert(str_contains($layout, '/mirrors/') && str_contains($layout, '/categories/') && str_contains($layout, '/providers/'), 'Primary navigation must expose the separate mirror, category and provider pages.');
$assert(str_contains($layout, '/status/') && str_contains($layout, '/guides/') && str_contains($layout, '/contribute/'), 'Primary navigation must expose status, guides and contribution pages.');
$assert(str_contains($htaccess, 'RewriteRule ^mirrors/?$ mirrors.php') && str_contains($htaccess, 'RewriteRule ^categories/?$ categories.php'), 'Clean directory routes are missing.');
$assert(str_contains($htaccess, 'RewriteRule ^providers/?$ providers.php') && str_contains($htaccess, 'RewriteRule ^contribute/?$ contribute.php'), 'Clean provider or contribution route is missing.');
$assert(str_contains($mirrorsDirectory, 'COUNT(*)') && str_contains($mirrorsDirectory, 'LIMIT {$perPage} OFFSET {$offset}'), 'Mirror directory must count and paginate results.');
$assert(str_contains($mirrorsDirectory, 'category_id') && str_contains($mirrorsDirectory, 'protocol') && str_contains($mirrorsDirectory, 'mirror_status'), 'Mirror directory filters are incomplete.');
$assert(str_contains($mirrorsDirectory, 'provider_id') && str_contains($mirrorsDirectory, "type = 'provider'") && str_contains($mirrorsDirectory, "LOWER(SUBSTRING_INDEX(m.url, '://', 1))"), 'Provider directory filtering must be provider-only and constrained by HTTP/HTTPS Base URL.');
$assert(str_contains($softwareDirectory, "c.type='software'") && str_contains($providerDirectory, "c.type='provider'"), 'Software categories and providers must have separate pages.');
$assert(str_contains($contributePage, '/suggest.php') && str_contains($contributePage, 'github.com/MirrorHub-IR/MirrorHub'), 'Contribution page must link to the submission form and source repository.');
$assert(str_contains($homePage, 'home-explore-grid') && str_contains($homePage, 'home-search'), 'Homepage discovery layout is missing.');
$assert(str_contains($siteJs, "event.key === 'Escape'") && str_contains($siteJs, "aria-expanded"), 'Responsive navigation must support Escape and expose its expanded state.');
$assert(str_contains($sitemap, "baseUrl . '/mirrors/'") && str_contains($sitemap, "baseUrl . '/providers/'"), 'Sitemap must include the new directory landing pages.');
$assert(str_contains($backupAdmin, 'backupCollectUploadFiles') && str_contains($backupAdmin, "'files' => \$uploadArchive"), 'Backup export must include the uploads manifest.');
$assert(str_contains($backupAdmin, 'backupValidateUploadFiles') && str_contains($backupAdmin, 'backupRestoreUploadFiles'), 'Backup import must validate and restore uploaded files.');
$assert(str_contains($backupAdmin, 'ZipArchive') && str_contains($backupAdmin, 'backupValidateZipUploadFiles') && str_contains($backupAdmin, "'file_storage' => \$exportFormat"), 'ZIP backup export and secure ZIP restore support are missing.');
$assert(str_contains($backupAdmin, '100 * 1024 * 1024') && str_contains($backupAdmin, "['json', 'zip']"), 'Backup upload must accept JSON/ZIP up to the documented 100 MB limit.');
$assert(str_contains($backupAdmin, 'backupNormalizeUploadPath') && str_contains($backupAdmin, 'content_base64') && str_contains($backupAdmin, 'sha256'), 'Uploaded-file archives must validate safe relative paths and integrity hashes.');
$assert(str_contains($siteJs, "document.addEventListener('paste'") && str_contains($siteJs, 'clipboardData.items') && str_contains($siteJs, 'DataTransfer') && str_contains($siteJs, 'is-dragging'), 'Global upload areas must support clipboard files/items, drag/drop and selected-file updates.');
$assert(str_contains($appJs, "['json', 'zip']") && str_contains($appJs, '100 * 1024 * 1024'), 'Backup import UI must accept ZIP and JSON up to 100 MB.');
$assert(str_contains($monitorAdmin, 'name="scheme"') && str_contains($monitorAdmin, 'mon-card-grid') && str_contains($monitorAdmin, 'monitorUrlScheme'), 'Admin monitor page must provide URL-scheme filtering and responsive cards.');
$assert(str_contains($monitoring, "LOWER(TRIM(m.url)) LIKE 'http://%' OR LOWER(TRIM(m.url)) LIKE 'https://%'") && str_contains($monitoring, 'function monitorUrlScheme'), 'Monitoring cycle and summaries must only include HTTP/HTTPS URLs.');
$assert(str_contains($adminStyles, '.admin-sidebar { overflow-x: hidden; }') && str_contains($adminStyles, '.admin-nav { overflow-x: hidden;'), 'Admin sidebar must not expose horizontal overflow.');
$assert(str_contains($adminHelp, 'Base URL') && str_contains($adminHelp, 'Ctrl+V') && str_contains($adminHelp, 'SVG'), 'In-panel admin guide must explain category base URLs, SVG and file paste.');
$assert(str_contains($adminGuide, 'Base URL') && str_contains($adminGuide, 'uploads/') && str_contains($adminGuide, 'HTTPS') && str_contains($adminGuide, 'ZIP') && str_contains($adminGuide, 'پینگ'), 'Persian admin guide must document provider filtering, ping and JSON/ZIP backups.');
$assert(str_contains($adminStyles, 'MirrorHub admin responsive hardening') && str_contains($adminStyles, '@media (max-width: 620px)'), 'Admin screens must include global responsive hardening.');
$assert(str_contains($functions, 'providerUrlMatchesBaseUrl') && str_contains($layout, "'mirror.php', 'mirrors.php'"), 'Ping and provider URL matching must be wired into public routes.');

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $cmsTestPdo = new PDO('sqlite::memory:');
    $cmsTestPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $cmsTestPdo->exec('CREATE TABLE cms_articles (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL)');
    $cmsTestPdo->exec("INSERT INTO cms_articles (title) VALUES ('مستند آزمایشی')");
    $cmsTestId = (int)$cmsTestPdo->lastInsertId();
    $assert(cmsDeleteArticle($cmsTestPdo, $cmsTestId), 'CMS deletion must return true when one article is removed.');
    $assert(!cmsDeleteArticle($cmsTestPdo, $cmsTestId), 'CMS deletion must report false when an article does not exist.');
    $assert(!cmsDeleteArticle($cmsTestPdo, 0), 'CMS deletion must reject non-positive article ids.');
}

require_once $root . '/includes/api-v1.php';
$validApiSuggestion = mhApiValidateContribution([
    'name_fa' => 'میرور نمونه', 'urls' => ['https://mirror.example.invalid/repo', 'https://mirror.example.invalid/repo'],
    'protocols' => ['https'], 'phone' => '+989121234567', 'website' => '',
]);
$assert($validApiSuggestion['valid'] && count($validApiSuggestion['data']['urls']) === 1, 'API contribution must deduplicate valid URLs.');
$invalidApiSuggestion = mhApiValidateContribution(['name_fa' => 'bad', 'urls' => ['file:///etc/passwd'], 'protocols' => ['invalid'], 'phone' => '1']);
$assert(!$invalidApiSuggestion['valid'], 'API contribution must reject unsafe schemes, bad protocols and bad phone numbers.');
$apiPageCap = mhApiPage(['page' => '999999', 'per_page' => '999']);
$assert($apiPageCap['page'] === 10000 && $apiPageCap['per_page'] === 100, 'API pagination caps are incorrect.');

if ($failures) {
    fwrite(STDERR, "Smoke tests failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}
echo "Security and regression smoke tests passed.\n";
