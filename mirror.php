<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/monitoring.php';
require_once 'includes/layout.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$slug = function_exists('mb_substr') ? mb_substr($slug, 0, 120, 'UTF-8') : substr($slug, 0, 120);
$stmt = $pdo->prepare("SELECT * FROM mirrors WHERE slug = ? AND status = 'active' LIMIT 1");
$stmt->execute([$slug]);
$mirror = $stmt->fetch();

if (!$mirror) {
    http_response_code(404);
    $GLOBALS['pageSeo'] = [
        'title' => 'میرور پیدا نشد | MirrorHub',
        'description' => 'میرور موردنظر پیدا نشد یا در حال حاضر فعال نیست.',
        'robots' => 'noindex,follow',
    ];
    renderHeader('میرور پیدا نشد');
    echo '<section class="section"><h1 class="section-title">میرور پیدا نشد</h1><p class="category-desc">این میرور وجود ندارد یا در حال حاضر فعال نیست.</p><p><a class="btn btn-primary" href="' . htmlspecialchars(rtrim(SITE_URL, '/') . '/search.php', ENT_QUOTES, 'UTF-8') . '">جست‌وجوی میرورها</a></p></section>';
    renderFooter();
    exit;
}

$mirrorId = (int)$mirror['id'];
$names = mirrorDisplayName($mirror);
$categoryMap = getMirrorCategoryTagsMap($pdo, [$mirrorId]);
$categories = $categoryMap[$mirrorId] ?? [];
$protocols = getMirrorProtocols($pdo, $mirrorId);
$statusMap = monitorStatusMap($pdo, [$mirrorId]);
$monitorStatus = $statusMap[$mirrorId] ?? null;
$health = null;
$history = [];
try {
    $healthStmt = $pdo->prepare("SELECT * FROM mirror_repository_health WHERE mirror_id = ? LIMIT 1");
    $healthStmt->execute([$mirrorId]);
    $health = $healthStmt->fetch() ?: null;
} catch (Throwable $e) { $health = null; }
try {
    $historyStmt = $pdo->prepare("SELECT checked_at, status, response_ms, http_code, method, error FROM mirror_checks WHERE mirror_id = ? ORDER BY checked_at DESC LIMIT 10");
    $historyStmt->execute([$mirrorId]);
    $history = $historyStmt->fetchAll();
} catch (Throwable $e) { $history = []; }

$healthStatus = (string)($health['status'] ?? 'not_configured');
$healthLabels = ['healthy' => 'متادیتا به‌روز', 'stale' => 'متادیتا قدیمی', 'invalid' => 'مسیر یا فایل نامعتبر', 'error' => 'خطای بررسی متادیتا', 'unknown' => 'وضعیت نامشخص', 'not_configured' => 'تنظیم نشده'];
$healthClasses = ['healthy' => 'badge-success', 'stale' => 'badge-warning', 'invalid' => 'badge-danger', 'error' => 'badge-danger', 'unknown' => 'badge-default', 'not_configured' => 'badge-default'];
$healthConfigured = !empty($health['profile']) && $health['profile'] !== 'http_only';
$status = (string)($monitorStatus['status'] ?? 'unknown');
$statusClasses = ['up' => 'badge-success', 'slow' => 'badge-warning', 'down' => 'badge-danger', 'unknown' => 'badge-default'];
$statusLabel = monitorStatusFa($status);
$baseUrl = rtrim((string)$mirror['url'], '/');
$urlIsHttp = filter_var((string)$mirror['url'], FILTER_VALIDATE_URL) !== false
    && in_array(strtolower((string)parse_url((string)$mirror['url'], PHP_URL_SCHEME)), ['http', 'https'], true);
if (!$urlIsHttp) $baseUrl = (string)$mirror['url'];

$repoTemplates = [
    'debian' => "# Debian — /etc/apt/sources.list.d/mirrorhub.sources\n# Replace <CODENAME> and verify the selected Debian suite.\nTypes: deb\nURIs: " . $baseUrl . "\nSuites: <CODENAME>\nComponents: main\n",
    'ubuntu' => "# Ubuntu — /etc/apt/sources.list.d/mirrorhub.sources\n# Replace <CODENAME> and verify the selected Ubuntu release.\nTypes: deb\nURIs: " . $baseUrl . "\nSuites: <CODENAME>\nComponents: main universe\n",
    'alpine' => "# Alpine — add entries to /etc/apk/repositories after verifying version and architecture\n" . $baseUrl . "/v<ALPINE_VERSION>/main\n" . $baseUrl . "/v<ALPINE_VERSION>/community\n",
    'arch' => "# Arch Linux — add this line to the matching section in /etc/pacman.d/mirrorlist\nServer = " . $baseUrl . "/\$repo/os/\$arch\n",
    'rpm' => "# Generic RPM .repo template — confirm the exact path for your distribution first.\n[mirrorhub]\nname=MirrorHub repository\nbaseurl=" . $baseUrl . "/\$releasever/\$basearch/\nenabled=1\ngpgcheck=1\n",
    'url' => $baseUrl . "\n",
];
$description = trim(strip_tags((string)($mirror['description'] ?? '')));
if ($description === '') $description = 'جزئیات، پروتکل‌ها و وضعیت ثبت‌شدهٔ میرور ' . $names['fa'] . ' در MirrorHub.';
$description = function_exists('mb_substr') ? mb_substr($description, 0, 180, 'UTF-8') : substr($description, 0, 180);

$GLOBALS['pageSeo'] = [
    'title' => $names['fa'] . ' | جزئیات میرور در MirrorHub',
    'description' => $description,
    'canonical' => rtrim(SITE_URL, '/') . '/mirror.php?slug=' . rawurlencode((string)$mirror['slug']),
    'breadcrumbs' => array_merge(
        [['name' => 'خانه', 'url' => rtrim(SITE_URL, '/') . '/']],
        array_map(fn($cat) => ['name' => (string)$cat['name'], 'url' => rtrim(SITE_URL, '/') . '/category.php?slug=' . rawurlencode((string)$cat['slug'])], $categories),
        [['name' => $names['fa'], 'url' => rtrim(SITE_URL, '/') . '/mirror.php?slug=' . rawurlencode((string)$mirror['slug'])]]
    ),
];
renderHeader($names['fa']);
?>

<nav class="breadcrumb" aria-label="مسیر صفحه">
    <a href="<?= SITE_URL ?>/index.php">خانه</a><span class="separator">/</span><a href="<?= SITE_URL ?>/search.php">میرورها</a><span class="separator">/</span><span class="current"><?= htmlspecialchars($names['fa'], ENT_QUOTES, 'UTF-8') ?></span>
</nav>

<section class="section mirror-detail-intro">
    <span class="hero-kicker">MirrorHub · جزئیات میرور</span>
    <div class="section-header">
        <div class="mirror-titles"><h1 class="section-title"><?= htmlspecialchars($names['fa'], ENT_QUOTES, 'UTF-8') ?></h1>
            <?php if ($names['en'] && $names['en'] !== $names['fa']): ?><p class="mirror-name-en" dir="ltr"><?= htmlspecialchars($names['en'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        </div>
        <div class="mirror-actions"><a class="btn btn-primary" href="<?= htmlspecialchars((string)$mirror['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">بازکردن میرور</a><?= pingButton((string)$mirror['url']) ?></div>
    </div>
    <?php if (!empty($mirror['description'])): ?><p class="category-desc"><?= nl2br(htmlspecialchars((string)$mirror['description'], ENT_QUOTES, 'UTF-8')) ?></p><?php endif; ?>
    <a class="mirror-detail-url" href="<?= htmlspecialchars((string)$mirror['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars((string)$mirror['url'], ENT_QUOTES, 'UTF-8') ?></a>
    <div class="mirror-meta"><?= protocolBadges($protocols) ?>
        <?php foreach ($categories as $cat): ?><a class="badge badge-primary" href="<?= SITE_URL ?>/category.php?slug=<?= rawurlencode((string)$cat['slug']) ?>"><?= htmlspecialchars((string)$cat['name'], ENT_QUOTES, 'UTF-8') ?><?= ($cat['type'] ?? '') === 'provider' ? ' · ارائه‌دهنده' : '' ?></a><?php endforeach; ?>
    </div>
</section>

<section class="section mirror-detail-grid">
    <article class="card mirror-detail-panel">
        <h2 class="section-title">وضعیت HTTP</h2>
        <div class="mirror-detail-stats">
            <div class="mirror-detail-stat"><small>وضعیت ثبت‌شده</small><strong><span class="badge <?= $statusClasses[$status] ?? 'badge-default' ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span></strong></div>
            <div class="mirror-detail-stat"><small>آخرین زمان پاسخ</small><strong><?= isset($monitorStatus['last_response_ms']) && $monitorStatus['last_response_ms'] !== null ? number_format((int)$monitorStatus['last_response_ms']) . ' ms' : '—' ?></strong></div>
            <div class="mirror-detail-stat"><small>آپتایم ثبت‌شده</small><strong><?= isset($monitorStatus['uptime_percent']) && $monitorStatus['uptime_percent'] !== null ? number_format((float)$monitorStatus['uptime_percent'], 1) . '٪' : '—' ?></strong></div>
            <div class="mirror-detail-stat"><small>آخرین بررسی</small><strong><?= !empty($monitorStatus['last_checked_at']) ? htmlspecialchars(monitorFaDateTime($monitorStatus['last_checked_at']), ENT_QUOTES, 'UTF-8') : 'هنوز بررسی نشده' ?></strong></div>
        </div>
    </article>
    <article class="card mirror-detail-panel">
        <h2 class="section-title">سلامت متادیتای مخزن</h2>
        <div class="mirror-detail-stats">
            <div class="mirror-detail-stat"><small>نتیجهٔ بررسی</small><strong><span class="badge <?= $healthClasses[$healthStatus] ?? 'badge-default' ?>"><?= htmlspecialchars($healthLabels[$healthStatus] ?? $healthLabels['unknown'], ENT_QUOTES, 'UTF-8') ?></span></strong></div>
            <div class="mirror-detail-stat"><small>پروفایل بررسی</small><strong><?= htmlspecialchars((string)($health['profile'] ?? 'http_only'), ENT_QUOTES, 'UTF-8') ?></strong></div>
            <div class="mirror-detail-stat"><small>سن متادیتا</small><strong><?= isset($health['age_seconds']) && $health['age_seconds'] !== null ? htmlspecialchars(monitorDurationFa((int)$health['age_seconds']), ENT_QUOTES, 'UTF-8') : '—' ?></strong></div>
            <div class="mirror-detail-stat"><small>آخرین بررسی متادیتا</small><strong><?= !empty($health['last_checked_at']) ? htmlspecialchars(monitorFaDateTime($health['last_checked_at']), ENT_QUOTES, 'UTF-8') : 'هنوز بررسی نشده' ?></strong></div>
        </div>
        <?php if (!empty($health['message'])): ?><p class="repo-config-note"><?= htmlspecialchars((string)$health['message'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <?php if (!$healthConfigured): ?><p class="repo-config-note">برای نمایش نتیجهٔ متادیتا، مدیر باید پروفایل مناسب این میرور را در پنل مدیریت تنظیم کند. وضعیت HTTP به‌تنهایی سلامت بسته‌ها یا امضاها را تأیید نمی‌کند.</p><?php endif; ?>
    </article>
</section>

<section class="section">
    <div class="section-header"><h2 class="section-title">قالب تنظیم مخزن</h2><span class="badge">قابل کپی و ویرایش</span></div>
    <div class="card repo-config-panel">
        <div class="repo-config-controls">
            <label for="repo-config-profile">توزیع / قالب
                <select id="repo-config-profile">
                    <option value="debian">Debian (APT)</option><option value="ubuntu">Ubuntu (APT)</option><option value="alpine">Alpine Linux (APK)</option><option value="arch">Arch Linux (Pacman)</option><option value="rpm">توزیع‌های RPM (قالب عمومی)</option><option value="url">فقط آدرس میرور</option>
                </select>
            </label>
            <button type="button" class="btn btn-primary" id="copy-repo-config">کپی قالب</button>
        </div>
        <pre class="repo-config-output" id="repo-config-output" tabindex="0"><?= htmlspecialchars($repoTemplates['debian'], ENT_QUOTES, 'UTF-8') ?></pre>
        <p class="repo-config-note">این متن‌ها قالب راه‌اندازی‌اند، نه تأیید سازگاری خودکار میرور با توزیع انتخاب‌شده. پیش از استفاده، مسیر واقعی مخزن، نسخهٔ توزیع، معماری و اجزای لازم را بررسی کنید. امضای بسته‌ها را غیرفعال نکنید و کلیدهای ناشناس را اضافه نکنید.</p>
        <p class="repo-config-note" id="repo-config-status" role="status" aria-live="polite"></p>
    </div>
</section>

<section class="section">
    <div class="section-header"><h2 class="section-title">تاریخچهٔ بررسی HTTP</h2><span class="badge"><?= count($history) ?> بررسی اخیر</span></div>
    <?php if (!$history): ?>
        <p class="empty-state">هنوز سابقه‌ای از بررسی HTTP ثبت نشده است.</p>
    <?php else: ?>
        <div class="card mirror-history-wrap"><table class="mirror-history-table">
            <thead><tr><th>زمان</th><th>وضعیت</th><th>کد HTTP</th><th>زمان پاسخ</th><th>روش</th><th>خطا</th></tr></thead>
            <tbody><?php foreach ($history as $item): ?>
                <?php $historyClass = ['up' => 'badge-success', 'slow' => 'badge-warning', 'down' => 'badge-danger'][$item['status']] ?? 'badge-default'; ?>
                <tr><td><?= htmlspecialchars(monitorFaDateTime($item['checked_at']), ENT_QUOTES, 'UTF-8') ?></td><td><span class="badge <?= $historyClass ?>"><?= htmlspecialchars(monitorStatusFa((string)$item['status']), ENT_QUOTES, 'UTF-8') ?></span></td><td><?= $item['http_code'] !== null ? (int)$item['http_code'] : '—' ?></td><td><?= $item['response_ms'] !== null ? number_format((int)$item['response_ms']) . ' ms' : '—' ?></td><td><?= htmlspecialchars((string)($item['method'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string)($item['error'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>

<section class="section"><div class="mirror-detail-backlinks"><a class="btn btn-outline" href="<?= SITE_URL ?>/search.php">بازگشت به جست‌وجو</a><a class="btn btn-outline" href="<?= SITE_URL ?>/status.php">وضعیت همهٔ سرورها</a></div></section>

<script type="application/json" id="repo-config-data"><?= json_encode($repoTemplates, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script>
(function () {
    'use strict';
    const dataNode = document.getElementById('repo-config-data');
    const profile = document.getElementById('repo-config-profile');
    const output = document.getElementById('repo-config-output');
    const copyButton = document.getElementById('copy-repo-config');
    const status = document.getElementById('repo-config-status');
    if (!dataNode || !profile || !output || !copyButton || !status) return;
    let templates = {};
    try { templates = JSON.parse(dataNode.textContent || '{}'); } catch (error) { status.textContent = 'بارگذاری قالب‌ها ناموفق بود.'; return; }
    profile.addEventListener('change', function () {
        output.textContent = templates[profile.value] || templates.url || '';
        status.textContent = '';
    });
    copyButton.addEventListener('click', function () {
        if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
            status.textContent = 'کپی خودکار در این مرورگر در دسترس نیست؛ متن را انتخاب و کپی کنید.';
            return;
        }
        navigator.clipboard.writeText(output.textContent || '').then(function () {
            status.textContent = 'قالب کپی شد.';
        }).catch(function () { status.textContent = 'کپی خودکار انجام نشد؛ متن را به‌صورت دستی انتخاب کنید.'; });
    });
}());
</script>
<?php renderFooter(); ?>
