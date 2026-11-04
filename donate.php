<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/donate.php';
require_once __DIR__ . '/includes/layout.php';

ensureDonateSchema($pdo);
$settings = donateSettings($pdo);
$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

$GLOBALS['pageSeo'] = [
    'title' => 'حمایت از MirrorHub | Donate',
    'description' => 'با معرفی پروژه، مشارکت در توسعه و همراهی در کانال‌های ارتباطی به رشد MirrorHub کمک کنید.',
    'canonical' => rtrim(SITE_URL, '/') . '/donate.php',
    'breadcrumbs' => [
        ['name' => 'خانه', 'url' => rtrim(SITE_URL, '/') . '/'],
        ['name' => 'حمایت از پروژه'],
    ],
];

$channels = [];
if ($settings['telegram_url'] !== '' && donateValidateUrl($settings['telegram_url'])) {
    $channels[] = [
        'kind' => 'telegram',
        'title' => 'گفتگو در تلگرام',
        'label' => 'Telegram',
        'description' => 'برای گفتگو، دنبال کردن خبرها و ارتباط مستقیم با جامعهٔ پروژه.',
        'url' => $settings['telegram_url'],
    ];
}
if ($settings['github_url'] !== '' && donateValidateUrl($settings['github_url'])) {
    $channels[] = [
        'kind' => 'github',
        'title' => 'مشارکت در GitHub',
        'label' => 'GitHub',
        'description' => 'کد پروژه را ببینید، پیشنهاد بدهید، مسئله‌ها را گزارش کنید یا مشارکت کنید.',
        'url' => $settings['github_url'],
    ];
}
for ($i = 1; $i <= 4; $i++) {
    $name = trim($settings['channel_' . $i . '_name']);
    $url = trim($settings['channel_' . $i . '_url']);
    if ($name === '' || $url === '' || !donateValidateUrl($url)) continue;
    $channels[] = [
        'kind' => 'custom',
        'title' => $name,
        'label' => 'راه ارتباطی',
        'description' => trim($settings['channel_' . $i . '_description']) ?: 'از این مسیر با تیم MirrorHub در ارتباط باشید.',
        'url' => $url,
    ];
}

renderHeader('حمایت از MirrorHub');
?>

<div class="donate-page">
    <section class="donate-hero">
        <div class="donate-hero-glow donate-glow-one" aria-hidden="true"></div>
        <div class="donate-hero-glow donate-glow-two" aria-hidden="true"></div>
        <div class="donate-hero-copy">
            <span class="donate-eyebrow"><span class="donate-eyebrow-dot"></span> MIRRORHUB · COMMUNITY SUPPORT</span>
            <h1><?= $h($settings['page_title']) ?></h1>
            <p class="donate-subtitle"><?= $h($settings['page_subtitle']) ?></p>
            <p class="donate-intro"><?= $h($settings['page_intro']) ?></p>
            <div class="donate-hero-actions">
                <a class="donate-primary-link" href="#donate-contact"><?= icon('heart', 17) ?> همراه پروژه باشید <span aria-hidden="true">←</span></a>
                <a class="donate-secondary-link" href="<?= SITE_URL ?>/"><?= icon('home', 16) ?> بازگشت به MirrorHub</a>
            </div>
        </div>
        <div class="donate-hero-art" aria-hidden="true">
            <div class="donate-orbit donate-orbit-outer"></div>
            <div class="donate-orbit donate-orbit-inner"></div>
            <div class="donate-art-core">
                <span class="donate-art-logo">M</span>
                <span class="donate-art-heart"><?= icon('heart', 27) ?></span>
            </div>
            <span class="donate-art-chip chip-open">OPEN SOURCE</span>
            <span class="donate-art-chip chip-community">COMMUNITY</span>
            <span class="donate-art-spark spark-one">✳</span>
            <span class="donate-art-spark spark-two">✦</span>
        </div>
        <div class="donate-hero-foot">
            <span>OPEN KNOWLEDGE</span><span class="donate-foot-line"></span><span>SHARED INFRASTRUCTURE</span><span class="donate-foot-line"></span><span>BUILT TOGETHER</span>
        </div>
    </section>

    <section class="donate-values" aria-label="راه‌های همراهی با پروژه">
        <article class="donate-value-card">
            <span class="donate-value-icon"><?= icon('heart', 21) ?></span>
            <span class="donate-value-index">۰۱ / SUPPORT</span>
            <h2>حمایت و معرفی</h2>
            <p>MirrorHub را به دوستان و تیم‌هایی که به میرورهای قابل‌اعتماد نیاز دارند معرفی کنید.</p>
        </article>
        <article class="donate-value-card">
            <span class="donate-value-icon"><?= icon('activity', 21) ?></span>
            <span class="donate-value-index">۰۲ / CONTRIBUTE</span>
            <h2>مشارکت در توسعه</h2>
            <p>با گزارش باگ، پیشنهاد قابلیت و مشارکت در کد، به بهتر شدن پروژه کمک کنید.</p>
        </article>
        <article class="donate-value-card">
            <span class="donate-value-icon"><?= icon('users', 21) ?></span>
            <span class="donate-value-index">۰۳ / CONNECT</span>
            <h2>همراهی با جامعه</h2>
            <p>با ما در ارتباط باشید، تجربه‌هایتان را به اشتراک بگذارید و مسیر پروژه را شکل دهید.</p>
        </article>
    </section>

    <section class="donate-contact-section" id="donate-contact">
        <div class="donate-section-heading">
            <div>
                <span class="donate-section-kicker">STAY CONNECTED</span>
                <h2>از اینجا به ما وصل شوید</h2>
                <p>راه ارتباطی مناسب خودتان را انتخاب کنید؛ لینک‌ها توسط تیم پروژه به‌روز می‌شوند.</p>
            </div>
            <span class="donate-section-mark"><?= icon('activity', 28) ?></span>
        </div>

        <?php if ($channels): ?>
        <div class="donate-channel-grid">
            <?php foreach ($channels as $channel): ?>
            <a class="donate-channel-card donate-channel-<?= $h($channel['kind']) ?>" href="<?= $h($channel['url']) ?>" target="_blank" rel="noopener noreferrer">
                <span class="donate-channel-icon">
                    <?php if ($channel['kind'] === 'telegram'): ?>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.5 4.5 18.3 19c-.24 1.03-.86 1.28-1.74.8l-4.82-3.55-2.32 2.23c-.26.26-.48.48-.98.48l.35-4.9 8.92-8.06c.39-.35-.08-.55-.6-.2L6.1 12.77l-4.7-1.47c-1.02-.32-1.04-1.02.21-1.51L20 3.12c.86-.31 1.62.21 1.5 1.38Z"/></svg>
                    <?php elseif ($channel['kind'] === 'github'): ?>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 19c-4.3 1.4-4.3-2.5-6-3m12 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 19 4.77 5.07 5.07 0 0 0 18.91 1S17.73.65 15 2.48a13.38 13.38 0 0 0-7 0C5.27.65 4.09 1 4.09 1A5.07 5.07 0 0 0 4 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 8 18.13V22"/></svg>
                    <?php else: ?>
                    <?= icon('users', 22) ?>
                    <?php endif; ?>
                </span>
                <span class="donate-channel-content">
                    <span class="donate-channel-label"><?= $h($channel['label']) ?></span>
                    <strong><?= $h($channel['title']) ?></strong>
                    <span class="donate-channel-description"><?= $h($channel['description']) ?></span>
                </span>
                <span class="donate-channel-arrow" aria-hidden="true">↗</span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="donate-contact-empty">
            <span><?= icon('info', 22) ?></span>
            <div><strong>راه‌های ارتباطی به‌زودی اضافه می‌شوند</strong><p>تیم پروژه در حال آماده‌سازی مسیرهای ارتباطی است. بعداً دوباره به این صفحه سر بزنید.</p></div>
        </div>
        <?php endif; ?>
    </section>

    <section class="donate-bottom-note">
        <span class="donate-bottom-heart"><?= icon('heart', 20) ?></span>
        <div><strong>هر همراهی، حتی کوچک، ارزشمند است.</strong><p>از اینکه برای ساختن یک منبع آزاد و مفید کنار ما هستید، سپاسگزاریم.</p></div>
        <span class="donate-bottom-word">THANK YOU</span>
    </section>
</div>

<?php renderFooter(); ?>
