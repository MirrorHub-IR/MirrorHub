<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

$GLOBALS['pageSeo'] = [
    'title' => 'مشارکت در MirrorHub | پیشنهاد میرور و توسعهٔ پروژه',
    'description' => 'با پیشنهاد میرور جدید، گزارش مشکل، بهبود مستندات یا مشارکت در توسعه به رشد MirrorHub کمک کنید.',
    'canonical' => rtrim(SITE_URL, '/') . '/contribute/',
    'breadcrumbs' => [
        ['name' => 'خانه', 'url' => rtrim(SITE_URL, '/') . '/'],
        ['name' => 'مشارکت', 'url' => rtrim(SITE_URL, '/') . '/contribute/'],
    ],
];
renderHeader('مشارکت در پروژه');
?>
<section class="directory-hero contribute-hero">
    <span class="directory-eyebrow"><?= icon('users', 15) ?> جامعهٔ MirrorHub</span>
    <h1>با مشارکت شما، <span>میرورها بهتر پیدا می‌شوند</span></h1>
    <p>یک میرور تازه معرفی کنید، اطلاعات نادرست را گزارش دهید یا در توسعه و مستندسازی پروژه سهیم شوید.</p>
    <div class="directory-hero-actions">
        <a class="btn btn-primary" href="<?= SITE_URL ?>/suggest.php"><?= icon('plus-circle', 16) ?> پیشنهاد میرور جدید</a>
        <a class="btn btn-outline" href="https://github.com/MirrorHub-IR/MirrorHub" target="_blank" rel="noopener noreferrer"><?= icon('github', 16) ?> مخزن GitHub</a>
    </div>
</section>
<section class="section">
    <div class="section-header"><h2 class="section-title">راه‌های مشارکت</h2><span class="eyebrow">سه مسیر ساده برای همراهی</span></div>
    <div class="grid grid-cols-3 contribute-grid">
        <article class="card contribute-card"><span class="contribute-icon"><?= icon('server', 24) ?></span><h2>معرفی میرور</h2><p>آدرس‌ها، پروتکل‌ها و اطلاعات تماس میرور را ارسال کنید. پیشنهادها ابتدا بررسی می‌شوند و سپس در فهرست عمومی قرار می‌گیرند.</p><a href="<?= SITE_URL ?>/suggest.php">ثبت پیشنهاد میرور ←</a></article>
        <article class="card contribute-card"><span class="contribute-icon"><?= icon('book-open', 24) ?></span><h2>بهبود مستندات</h2><p>راهنمای نصب و تنظیم مخازن را بخوانید و برای دقیق‌تر شدن مراحل و رفع خطاهای رایج بازخورد بدهید.</p><a href="<?= SITE_URL ?>/guides/">مطالعهٔ راهنماها ←</a></article>
        <article class="card contribute-card"><span class="contribute-icon"><?= icon('code-2', 24) ?></span><h2>توسعهٔ پروژه</h2><p>کد را بررسی کنید، مشکل گزارش دهید یا برای بهبود رابط کاربری و قابلیت‌های پایش Pull Request ارسال کنید.</p><a href="https://github.com/MirrorHub-IR/MirrorHub" target="_blank" rel="noopener noreferrer">مشارکت در GitHub ←</a></article>
    </div>
</section>
<section class="section contribute-process">
    <div><span class="eyebrow">فرآیند بررسی</span><h2 class="section-title">بعد از ارسال پیشنهاد چه اتفاقی می‌افتد؟</h2><p>اطلاعات ارسالی به‌صورت مستقیم عمومی نمی‌شوند. مدیران جزئیات، دسترسی و دسته‌بندی پیشنهادی را بررسی می‌کنند؛ سپس در صورت تأیید، میرور به فهرست اضافه می‌شود.</p></div>
    <ol class="contribute-steps"><li><strong>۱. ارسال اطلاعات</strong><span>نام، لینک‌ها و پروتکل‌های دسترسی را وارد کنید.</span></li><li><strong>۲. بررسی مدیر</strong><span>اعتبار و تناسب اطلاعات بررسی می‌شود.</span></li><li><strong>۳. انتشار</strong><span>میرور تأییدشده در فهرست و پایش قرار می‌گیرد.</span></li></ol>
</section>
<?php renderFooter(); ?>
