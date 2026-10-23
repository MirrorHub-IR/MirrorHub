<?php
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
    if ($metaDescription === '') $metaDescription = "$siteName - مخزن میرورهای نرم‌افزاری";
    $canonicalUrl = trim((string)($seo['canonical'] ?? ''));
    $ogType = (($seo['og_type'] ?? '') === 'article') ? 'article' : 'website';
    $flashSuccess = flash('success');
    $flashError = flash('error');
    $currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($canonicalUrl !== ''): ?><link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
    <meta name="theme-color" content="#0e1410">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="icon" type="image/png" href="<?= asset('logo.png') ?>">
    <link rel="shortcut icon" type="image/png" href="<?= asset('logo.png') ?>">
    <link rel="apple-touch-icon" href="<?= asset('logo.png') ?>">
    <meta property="og:type" content="<?= htmlspecialchars($ogType, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:site_name" content="<?= htmlspecialchars($siteName) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <?php if (!empty($seo['published'])): ?><meta property="article:published_time" content="<?= htmlspecialchars((string)$seo['published'], ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
    <?php if (!empty($seo['modified'])): ?><meta property="article:modified_time" content="<?= htmlspecialchars((string)$seo['modified'], ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
    <meta property="og:image" content="<?= SITE_URL ?>/assets/logo.png">
</head>
<body class="<?= $isAdmin ? 'admin-layout' : 'public-layout' ?>">
    <header class="main-header">
        <div class="container">
            <a href="<?= SITE_URL ?>/<?= $isAdmin ? 'admin/dashboard.php' : 'index.php' ?>" class="logo">
                <img src="<?= asset('logo.png') ?>" alt="<?= htmlspecialchars($siteName) ?>" class="logo-mark" width="34" height="34">
                <span class="logo-text"><?= htmlspecialchars($siteName) ?></span>
            </a>
            <button type="button"
                    class="nav-toggle"
                    aria-label="باز کردن منو"
                    aria-expanded="false"
                    aria-controls="main-navigation">
                <span></span><span></span><span></span>
            </button>
            <nav id="main-navigation" class="main-nav">
                <?php if ($isAdmin): ?>
                    <a href="<?= SITE_URL ?>/admin/dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                        <?= icon('dashboard', 15) ?><span>داشبورد</span>
                    </a>
                    <a href="<?= SITE_URL ?>/admin/categories.php" class="<?= $currentPage === 'categories.php' ? 'active' : '' ?>">
                        <?= icon('folder', 15) ?><span>دسته‌بندی‌ها</span>
                    </a>
                    <a href="<?= SITE_URL ?>/admin/mirrors.php" class="<?= $currentPage === 'mirrors.php' ? 'active' : '' ?>">
                        <?= icon('server', 15) ?><span>میرورها</span>
                    </a>
                    <a href="<?= SITE_URL ?>/admin/pages.php" class="<?= $currentPage === 'pages.php' ? 'active' : '' ?>">
                        <?= icon('activity', 15) ?><span>مستندات</span>
                    </a>
                    <a href="<?= SITE_URL ?>/admin/monitor.php" class="<?= $currentPage === 'monitor.php' ? 'active' : '' ?>">
                        <?= icon('activity', 15) ?><span>پایش سرورها</span>
                    </a>
                    <a href="<?= SITE_URL ?>/admin/requests.php" class="<?= $currentPage === 'requests.php' ? 'active' : '' ?>">
                        <?= icon('inbox', 15) ?><span>درخواست‌ها</span>
                    </a>
                    <a href="<?= SITE_URL ?>/admin/backup.php" class="<?= $currentPage === 'backup.php' ? 'active' : '' ?>">
                        <?= icon('database', 15) ?><span>پشتیبان</span>
                    </a>
                    <a href="<?= SITE_URL ?>/admin/users.php" class="<?= $currentPage === 'users.php' ? 'active' : '' ?>">
                        <?= icon('users', 15) ?><span>کاربران</span>
                    </a>
                    <a href="<?= SITE_URL ?>/admin/login.php?action=logout" class="btn-logout" title="خروج از حساب">
                        <?= icon('log-out', 14) ?><span>خروج</span>
                    </a>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>/index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                        <?= icon('home', 15) ?><span>خانه</span>
                    </a>
                    <a href="<?= SITE_URL ?>/search.php" class="<?= $currentPage === 'search.php' ? 'active' : '' ?>">
                        <?= icon('search', 15) ?><span>جستجو</span>
                    </a>
                    <a href="<?= SITE_URL ?>/guides/" class="<?= $currentPage === 'docs.php' ? 'active' : '' ?>">
                        <?= icon('activity', 15) ?><span>مستندات</span>
                    </a>
                    <a href="<?= SITE_URL ?>/status.php" class="<?= $currentPage === 'status.php' ? 'active' : '' ?>">
                        <?= icon('activity', 15) ?><span>وضعیت سرورها</span>
                    </a>
                    <a href="<?= SITE_URL ?>/suggest.php" class="<?= $currentPage === 'suggest.php' ? 'active' : '' ?>">
                        <?= icon('plus-circle', 15) ?><span>پیشنهاد میرور</span>
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="container main-content">
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
        <?php endif; ?>
<?php
}

function renderFooter() {
?>
    </main>

    <footer class="main-footer">
        <div class="container">
            <div class="footer-brand">
                <img src="<?= asset('logo.png') ?>" alt="" class="footer-logo" width="34" height="34" loading="lazy">
                <div class="footer-brand-text">
                    <span class="footer-brand-name"><?= htmlspecialchars(SITE_NAME) ?></span>
                    <span class="footer-tagline">مخزن میرورهای نرم‌افزاری</span>
                </div>
            </div>
            <p class="footer-copy">&copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?>. تمامی حقوق محفوظ است.</p>
            <p class="footer-made">ساخته شده با <span class="heart"><?= icon('heart', 14) ?></span> در ایران</p>
        </div>
    </footer>

    <script>window.MH_SITE_URL = <?= json_encode(SITE_URL) ?>;</script>
    <script src="<?= asset('js/app.js') ?>"></script>
    <script src="<?= asset('js/ping.js') ?>"></script>
</body>
</html>
<?php
}