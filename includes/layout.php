<?php
require_once __DIR__ . '/jalali.php';
function renderHeader($title = '', $isAdmin = false) {
    if (!$isAdmin) {
        global $pdo;
        if (isset($pdo) && $pdo instanceof PDO) {
            recordVisit($pdo);
        }
    }

    $siteName = SITE_NAME;
    $seo = $GLOBALS['pageSeo'] ?? [];
    $metaTitle = trim((string)($seo['title'] ?? ''));
    $pageTitle = $metaTitle !== '' ? $metaTitle : ($title ? "$title | $siteName" : $siteName);
    $metaDescription = trim((string)($seo['description'] ?? ''));
    $currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($metaDescription === '') {
        $metaDescription = $title !== ''
            ? $title . ' در ' . $siteName . '؛ فهرست میرورهای نرم‌افزاری و وضعیت آن‌ها.'
            : $siteName . '؛ فهرست میرورهای نرم‌افزاری و راهنماهای استفاده از مخازن.';
    }
    $metaDescription = trim((string)(preg_replace('/\s+/u', ' ', $metaDescription) ?? $metaDescription));
    $metaDescription = function_exists('mb_substr')
        ? mb_substr($metaDescription, 0, 180, 'UTF-8')
        : substr($metaDescription, 0, 180);
    $robotsContent = trim((string)($seo['robots'] ?? 'index,follow'));
    if ($isAdmin) $robotsContent = 'noindex,nofollow';
    $canonicalUrl = trim((string)($seo['canonical'] ?? ''));
    $responseCode = http_response_code();
    $isNoindex = stripos($robotsContent, 'noindex') !== false;
    if ($canonicalUrl === '' && !$isAdmin && $currentPage !== 'search.php' && !$isNoindex && $responseCode !== 404) {
        $canonicalPath = ($currentPage === 'index.php' || $currentPage === '') ? '' : rawurlencode($currentPage);
        $canonicalUrl = rtrim(SITE_URL, '/') . '/' . $canonicalPath;
    }
    $extraStyles = is_array($seo['styles'] ?? null) ? $seo['styles'] : [];
    if (!$extraStyles && $currentPage === 'docs.php') $extraStyles = ['css/cms.css'];
    if (!$extraStyles && $isAdmin && $currentPage === 'pages.php') $extraStyles = ['css/cms.css'];
    if ($isAdmin) $extraStyles[] = 'css/admin.css';
    $extraStyles = array_values(array_unique($extraStyles));
    $ogType = (($seo['og_type'] ?? '') === 'article') ? 'article' : 'website';
    $siteUrl = rtrim(SITE_URL, '/') . '/';
    $socialImageUrl = rtrim(SITE_URL, '/') . '/assets/logo.png';
    $organizationId = rtrim(SITE_URL, '/') . '/#organization';
    $structuredData = [[
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        '@id' => $siteUrl,
        'name' => $siteName,
        'url' => $siteUrl,
        'inLanguage' => 'fa-IR',
        'publisher' => ['@id' => $organizationId],
    ], [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        '@id' => $organizationId,
        'name' => $siteName,
        'url' => $siteUrl,
        'logo' => ['@type' => 'ImageObject', 'url' => $socialImageUrl],
    ]];
    if (!$isAdmin && $canonicalUrl !== '') {
        $structuredData[] = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $pageTitle,
            'description' => $metaDescription,
            'url' => $canonicalUrl,
            'inLanguage' => 'fa-IR',
            'isPartOf' => ['@type' => 'WebSite', '@id' => $siteUrl],
        ];
    }
    if (!$isAdmin && is_array($seo['article_schema'] ?? null)) {
        $articleSchema = $seo['article_schema'];
        $articleSchema['@context'] = 'https://schema.org';
        $articleSchema['@type'] = $articleSchema['@type'] ?? 'Article';
        $structuredData[] = $articleSchema;
    }
    if (!$isAdmin && !empty($seo['breadcrumbs']) && is_array($seo['breadcrumbs'])) {
        $items = [];
        foreach (array_values($seo['breadcrumbs']) as $index => $crumb) {
            if (!is_array($crumb) || empty($crumb['name'])) continue;
            $item = ['@type' => 'ListItem', 'position' => count($items) + 1, 'name' => (string)$crumb['name']];
            if (!empty($crumb['url'])) $item['item'] = (string)$crumb['url'];
            $items[] = $item;
        }
        if (count($items) > 1) {
            $structuredData[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
        }
    }
    $flashSuccess = flash('success');
    $flashError = flash('error');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark">
    <link rel="preload" href="<?= asset('fonts/Vazirmatn-Variable.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="<?= htmlspecialchars($robotsContent, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($canonicalUrl !== ''): ?><link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
    <meta name="theme-color" content="#0e1410">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">
    <?php foreach ($extraStyles as $stylePath): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars(asset((string)$stylePath), ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>

    <link rel="icon" type="image/png" href="<?= asset('logo.png') ?>">
    <link rel="shortcut icon" type="image/png" href="<?= asset('logo.png') ?>">
    <link rel="apple-touch-icon" href="<?= asset('logo.png') ?>">
    <meta property="og:locale" content="fa_IR">
    <?php if ($canonicalUrl !== ''): ?><meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($socialImageUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="<?= htmlspecialchars($ogType, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:site_name" content="<?= htmlspecialchars($siteName) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <?php if (!empty($seo['published'])): ?><meta property="article:published_time" content="<?= htmlspecialchars((string)$seo['published'], ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
    <?php if (!empty($seo['modified'])): ?><meta property="article:modified_time" content="<?= htmlspecialchars((string)$seo['modified'], ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
    <meta property="og:image" content="<?= htmlspecialchars($socialImageUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image:alt" content="<?= htmlspecialchars($siteName . ' — نشان پروژه', ENT_QUOTES, 'UTF-8') ?>">
    <?php if (!$isAdmin): foreach ($structuredData as $schema): ?>
    <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
    <?php endforeach; endif; ?>
</head>
<body class="<?= $isAdmin ? 'admin-layout' : 'public-layout' ?>">
    <a class="skip-link" href="#main-content">رفتن به محتوای اصلی</a>
<?php if ($isAdmin && $currentPage !== 'login.php'): ?>
    <div class="admin-app">
        <aside class="admin-sidebar" aria-label="ناوبری مدیریت">
            <a href="<?= SITE_URL ?>/admin/dashboard.php" class="admin-sidebar-brand">
                <img src="<?= asset('logo.png') ?>" alt="" width="42" height="42">
                <span><strong><?= htmlspecialchars($siteName) ?></strong><small>کنسول مدیریت</small></span>
            </a>

            <div class="admin-sidebar-context">
                <span class="admin-sidebar-context-icon"><?= icon('dashboard', 17) ?></span>
                <span><small>محیط کاری</small><strong>مدیریت سامانه</strong></span>
            </div>

            <nav id="main-navigation" class="admin-nav" aria-label="منوی اصلی مدیریت">
                <span class="admin-nav-heading">نمای کلی</span>
                <a href="<?= SITE_URL ?>/admin/dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" <?= $currentPage === 'dashboard.php' ? 'aria-current="page"' : '' ?>>
                    <?= icon('dashboard', 18) ?><span>داشبورد</span>
                </a>
                <a href="<?= SITE_URL ?>/admin/help.php" class="<?= $currentPage === 'help.php' ? 'active' : '' ?>" <?= $currentPage === 'help.php' ? 'aria-current="page"' : '' ?>>
                    <?= icon('activity', 18) ?><span>راهنمای مدیریت</span>
                </a>

                <span class="admin-nav-heading">مدیریت محتوا</span>
                <a href="<?= SITE_URL ?>/admin/mirrors.php" class="<?= $currentPage === 'mirrors.php' ? 'active' : '' ?>" <?= $currentPage === 'mirrors.php' ? 'aria-current="page"' : '' ?>>
                    <?= icon('server', 18) ?><span>میرورها</span>
                </a>
                <a href="<?= SITE_URL ?>/admin/categories.php" class="<?= $currentPage === 'categories.php' ? 'active' : '' ?>" <?= $currentPage === 'categories.php' ? 'aria-current="page"' : '' ?>>
                    <?= icon('folder', 18) ?><span>دسته‌بندی‌ها</span>
                </a>
                <a href="<?= SITE_URL ?>/admin/pages.php" class="<?= $currentPage === 'pages.php' ? 'active' : '' ?>" <?= $currentPage === 'pages.php' ? 'aria-current="page"' : '' ?>>
                    <?= icon('activity', 18) ?><span>مستندات</span>
                </a>
                <a href="<?= SITE_URL ?>/admin/requests.php" class="<?= $currentPage === 'requests.php' ? 'active' : '' ?>" <?= $currentPage === 'requests.php' ? 'aria-current="page"' : '' ?>>
                    <?= icon('inbox', 18) ?><span>درخواست‌ها</span>
                </a>

                <span class="admin-nav-heading">پایش و زیرساخت</span>
                <a href="<?= SITE_URL ?>/admin/monitor.php" class="<?= $currentPage === 'monitor.php' ? 'active' : '' ?>" <?= $currentPage === 'monitor.php' ? 'aria-current="page"' : '' ?>>
                    <?= icon('activity', 18) ?><span>پایش میرورها</span>
                </a>
                <a href="<?= SITE_URL ?>/admin/backup.php" class="<?= $currentPage === 'backup.php' ? 'active' : '' ?>" <?= $currentPage === 'backup.php' ? 'aria-current="page"' : '' ?>>
                    <?= icon('database', 18) ?><span>پشتیبان‌گیری</span>
                </a>
                <a href="<?= SITE_URL ?>/admin/users.php" class="<?= $currentPage === 'users.php' ? 'active' : '' ?>" <?= $currentPage === 'users.php' ? 'aria-current="page"' : '' ?>>
                    <?= icon('users', 18) ?><span>کاربران</span>
                </a>

                <span class="admin-nav-heading">ارتباط با جامعه</span>
                <a href="<?= SITE_URL ?>/admin/donate.php" class="<?= $currentPage === 'donate.php' ? 'active' : '' ?>" <?= $currentPage === 'donate.php' ? 'aria-current="page"' : '' ?>>
                    <?= icon('heart', 18) ?><span>صفحهٔ Donate</span>
                </a>
            </nav>

            <div class="admin-sidebar-footer">
                <div class="admin-user-chip">
                    <span class="admin-user-avatar"><?= icon('users', 18) ?></span>
                    <span class="admin-user-meta"><strong><?= htmlspecialchars((string)($_SESSION['username'] ?? 'مدیر')) ?></strong><small>حساب مدیر</small></span>
                </div>
                <a href="<?= SITE_URL ?>/admin/login.php?action=logout" class="admin-logout-link">
                    <?= icon('log-out', 17) ?><span>خروج امن</span>
                </a>
            </div>
        </aside>

        <div class="admin-workspace">
            <header class="main-header admin-topbar">
                <div class="admin-topbar-leading">
                    <button type="button" class="nav-toggle" aria-label="باز کردن منوی مدیریت" aria-expanded="false" aria-controls="main-navigation">
                        <span></span><span></span><span></span>
                    </button>
                    <div class="admin-topbar-title">
                        <span>پنل مدیریت MirrorHub</span>
                        <strong><?= htmlspecialchars($title !== '' ? $title : 'مدیریت سامانه') ?></strong>
                    </div>
                </div>
                <div class="admin-topbar-actions">
                    <span class="admin-topbar-user"><?= icon('users', 16) ?><?= htmlspecialchars((string)($_SESSION['username'] ?? 'مدیر')) ?></span>
                    <a href="<?= SITE_URL ?>/" class="admin-view-site" target="_blank" rel="noopener noreferrer"><?= icon('activity', 15) ?><span>مشاهدهٔ سایت</span></a>
                </div>
            </header>
            <main id="main-content" class="container main-content admin-content" tabindex="-1">
<?php elseif ($isAdmin): ?>
    <header class="main-header admin-login-header">
        <div class="container">
            <a href="<?= SITE_URL ?>/" class="logo">
                <img src="<?= asset('logo.png') ?>" alt="<?= htmlspecialchars($siteName) ?>" class="logo-mark" width="34" height="34">
                <span class="logo-text"><?= htmlspecialchars($siteName) ?></span>
            </a>
        </div>
    </header>
    <main id="main-content" class="container main-content" tabindex="-1">
<?php else: ?>
    <header class="main-header">
        <div class="container">
            <a href="<?= SITE_URL ?>/index.php" class="logo">
                <img src="<?= asset('logo.png') ?>" alt="<?= htmlspecialchars($siteName) ?>" class="logo-mark" width="34" height="34">
                <span class="logo-text"><?= htmlspecialchars($siteName) ?></span>
            </a>
            <button type="button" class="nav-toggle" aria-label="باز کردن منو" aria-expanded="false" aria-controls="main-navigation">
                <span></span><span></span><span></span>
            </button>
            <nav id="main-navigation" class="main-nav" aria-label="ناوبری اصلی">
                <a href="<?= SITE_URL ?>/index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>" <?= $currentPage === 'index.php' ? 'aria-current="page"' : '' ?>>
                    <?= icon('home', 15) ?><span>خانه</span>
                </a>
                <a href="<?= SITE_URL ?>/search.php" class="<?= $currentPage === 'search.php' ? 'active' : '' ?>" <?= $currentPage === 'search.php' ? 'aria-current="page"' : '' ?>>
                    <?= icon('search', 15) ?><span>جستجو</span>
                </a>
                <a href="<?= SITE_URL ?>/guides/" class="<?= $currentPage === 'docs.php' ? 'active' : '' ?>" <?= $currentPage === 'docs.php' ? 'aria-current="page"' : '' ?>>
                    <?= icon('activity', 15) ?><span>مستندات</span>
                </a>
                <a href="<?= SITE_URL ?>/status.php" class="<?= $currentPage === 'status.php' ? 'active' : '' ?>" <?= $currentPage === 'status.php' ? 'aria-current="page"' : '' ?>>
                    <?= icon('activity', 15) ?><span>وضعیت سرورها</span>
                </a>
                <a href="<?= SITE_URL ?>/suggest.php" class="<?= $currentPage === 'suggest.php' ? 'active' : '' ?>" <?= $currentPage === 'suggest.php' ? 'aria-current="page"' : '' ?>>
                    <?= icon('plus-circle', 15) ?><span>پیشنهاد میرور</span>
                </a>
                <a href="<?= SITE_URL ?>/donate.php" class="nav-donate <?= $currentPage === 'donate.php' ? 'active' : '' ?>" <?= $currentPage === 'donate.php' ? 'aria-current="page"' : '' ?>>
                    <?= icon('heart', 15) ?><span>حمایت از پروژه</span>
                </a>
            </nav>
        </div>
    </header>
    <main id="main-content" class="container main-content" tabindex="-1">
<?php endif; ?>
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
        <?php endif; ?>
<?php
}

function renderFooter() {
    $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $adminRoute = strpos((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/') !== false;
    $adminShell = $adminRoute && $scriptName !== 'login.php';
?>
    </main>
    <?php if ($adminShell): ?>
    <footer class="admin-footer">
        <span><?= htmlspecialchars(SITE_NAME) ?> <span aria-hidden="true">·</span> پنل مدیریت</span>
        <span>مدیریت امن و یکپارچهٔ میرورها</span>
    </footer>
        </div>
    </div>
    <?php else: ?>
    <footer class="main-footer">
        <div class="container">
            <div class="footer-brand">
                <img src="<?= asset('logo.png') ?>" alt="" class="footer-logo" width="34" height="34" loading="lazy">
                <div class="footer-brand-text">
                    <span class="footer-brand-name"><?= htmlspecialchars(SITE_NAME) ?></span>
                    <span class="footer-tagline">مخزن میرورهای نرم‌افزاری</span>
                </div>
            </div>
            <p class="footer-copy">&copy; <?= jalaliFormatDate(time(), 'Y') ?> <?= htmlspecialchars(SITE_NAME) ?>. تمامی حقوق محفوظ است.</p>
            <p class="footer-made">ساخته شده با <span class="heart"><?= icon('heart', 14) ?></span> در ایران · <a href="<?= SITE_URL ?>/donate.php">حمایت از پروژه</a></p>
        </div>
    </footer>
    <?php endif; ?>

    <?php
        $adminInteractionPages = ['categories.php', 'mirrors.php', 'pages.php', 'monitor.php', 'requests.php', 'backup.php', 'users.php'];
        $needsAdminInteractions = $adminRoute && in_array($scriptName, $adminInteractionPages, true);
        $needsPing = !$adminRoute && in_array($scriptName, ['index.php', 'search.php', 'category.php'], true);
    ?>
    <script>window.MH_SITE_URL = <?= json_encode(SITE_URL, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script src="<?= asset('js/site.js') ?>" defer></script>
    <?php if ($needsAdminInteractions): ?>
        <script src="<?= asset('js/app.js') ?>" defer></script>
    <?php endif; ?>
    <?php if ($scriptName === 'suggest.php'): ?>
        <script src="<?= asset('js/suggest.js') ?>" defer></script>
    <?php endif; ?>
    <?php if ($needsPing): ?>
        <script src="<?= asset('js/ping.js') ?>" defer></script>
    <?php endif; ?>
</body>
</html>
<?php
}
