<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireAdmin();
renderHeader('راهنمای مدیریت', true);
?>

<div class="admin-guide-page">
    <section class="admin-guide-hero">
        <div class="admin-guide-hero-copy">
            <span class="admin-guide-eyebrow"><?= icon('activity', 15) ?> مرکز راهنمای مدیریت</span>
            <h1>راهنمای مدیریت MirrorHub</h1>
            <p>مراحل اصلی نگهداری سامانه، مدیریت محتوا و تنظیم پایش را از این صفحه دنبال کنید. هر بخش به صفحهٔ مرتبط در پنل دسترسی مستقیم دارد.</p>
            <div class="admin-guide-meta">
                <span><?= icon('check', 15) ?> راهنمای عملیاتی مدیر</span>
                <span><?= icon('activity', 15) ?> مطالعهٔ مرحله‌ای و سریع</span>
            </div>
        </div>
        <div class="admin-guide-hero-mark" aria-hidden="true"><?= icon('activity', 54) ?></div>
    </section>

    <nav class="admin-guide-quick-nav" aria-label="دسترسی سریع به راهنما">
        <a href="#guide-documents"><?= icon('activity', 16) ?><span>مستندات</span></a>
        <a href="#guide-monitoring"><?= icon('activity', 16) ?><span>زمان‌بندی پایش</span></a>
        <a href="#guide-settings"><?= icon('activity', 16) ?><span>تنظیمات پایش</span></a>
        <a href="#guide-categories"><?= icon('folder', 16) ?><span>دسته‌بندی و میرورها</span></a>
        <a href="#guide-security"><?= icon('check', 16) ?><span>پشتیبان و امنیت</span></a>
        <a href="#guide-repository"><?= icon('check', 16) ?><span>سلامت مخزن</span></a>
        <a href="#guide-uploads"><?= icon('image', 16) ?><span>آپلود فایل</span></a>
    </nav>

    <div class="admin-guide-grid">
        <article class="admin-guide-card" id="guide-documents">
            <div class="admin-guide-card-heading">
                <span class="admin-guide-number">۰۱</span>
                <div><span class="admin-guide-kicker">محتوا و مستندات</span><h2>نوشتن راهنمای نصب یا تغییر میرور</h2></div>
            </div>
            <ol class="admin-guide-steps">
                <li>از منوی مدیریت وارد «مستندات» شوید و «مقاله جدید» را انتخاب کنید.</li>
                <li>عنوان روشن، نامک یکتا و خلاصه‌ای کوتاه بنویسید.</li>
                <li>دستهٔ مرتبط را در فیلد «راهنمای مربوط به کدام دسته است؟» انتخاب کنید؛ این فهرست شامل دسته‌های نرم‌افزار و ارائه‌دهنده است.</li>
                <li>متن را با Markdown بنویسید و تیترها، فهرست‌ها و بلوک‌های کد را بازبینی کنید؛ سپس وضعیت را روی «منتشرشده» قرار دهید.</li>
                <li>برای نمایش وضعیت زندهٔ میرورها، در یک خط مستقل از الگوی <code>:::health ubuntu</code> استفاده کنید و نامک واقعی دسته را جایگزین کنید.</li>
            </ol>
            <p class="admin-guide-note">هر مقاله به یک دسته متصل می‌شود و هر دسته می‌تواند چند راهنمای منتشرشده داشته باشد. راهنما در صفحهٔ همان دسته نمایش داده می‌شود؛ برای مقالهٔ عمومی، گزینهٔ «بدون اتصال به دسته» را انتخاب کنید. بلوک وضعیت زندهٔ <code>:::health</code> فقط برای دسته‌های نرم‌افزار قابل استفاده است. حذف مقاله دائمی است.</p>
            <a class="btn btn-primary" href="<?= SITE_URL ?>/admin/pages.php"><?= icon('activity', 15) ?> مدیریت مستندات</a>
        </article>

        <article class="admin-guide-card" id="guide-monitoring">
            <div class="admin-guide-card-heading">
                <span class="admin-guide-number">۰۲</span>
                <div><span class="admin-guide-kicker">زمان‌بندی و اجرا</span><h2>تنظیم Cron و اجرای خودکار</h2></div>
            </div>
            <ol class="admin-guide-steps">
                <li>در صفحهٔ «پایش میرورها»، مقدار «فاصلهٔ اجرای واقعی پایش» را انتخاب کنید.</li>
                <li>تنظیمات را ذخیره کنید تا دستور Cron بر اساس بازهٔ جدید ساخته شود.</li>
                <li>دستور پیشنهادی را در cPanel یا زمان‌بند سرور ثبت کنید. در صورت تغییر فاصله، دستور قبلی را جایگزین کنید.</li>
                <li>صفحهٔ عمومی وضعیت، بازهٔ انتخاب‌شده را نمایش می‌دهد. فراخوانی زودتر از موعد باعث اجرای چرخهٔ جدید نمی‌شود.</li>
            </ol>
            <p class="admin-guide-note">برای بازه‌های ۲۴ ساعت و بیشتر، زمان‌بند روزانه برنامه را فراخوانی می‌کند و خود MirrorHub فاصلهٔ واقعی چرخه را کنترل می‌کند. اجرای دستی برای آزمایش است، نه جایگزین Cron.</p>
            <div class="admin-guide-actions">
                <a class="btn btn-primary" href="<?= SITE_URL ?>/admin/monitor.php"><?= icon('activity', 15) ?> تنظیمات پایش</a>
                <a class="btn btn-outline" href="<?= SITE_URL ?>/status.php" target="_blank" rel="noopener noreferrer">صفحهٔ عمومی وضعیت</a>
            </div>
        </article>

        <article class="admin-guide-card" id="guide-settings">
            <div class="admin-guide-card-heading">
                <span class="admin-guide-number">۰۳</span>
                <div><span class="admin-guide-kicker">پیکربندی پایش</span><h2>معنی پارامترهای اصلی</h2></div>
            </div>
            <dl class="admin-guide-definitions">
                <div><dt>مهلت پاسخ</dt><dd>مدت انتظار برای پاسخ HTTP/HTTPS؛ در صورت خطا، درخواست یک بار دیگر امتحان می‌شود.</dd></div>
                <div><dt>آستانهٔ کندی</dt><dd>زمانی که عبور از آن وضعیت را «کند» می‌کند؛ کند بودن به معنی قطع بودن نیست.</dd></div>
                <div><dt>حداکثر میرور در هر چرخه</dt><dd>سقف بررسی در هر اجرا؛ میرورهای قدیمی‌تر در چرخه‌های بعدی اولویت می‌گیرند.</dd></div>
                <div><dt>خطاهای متوالی</dt><dd>برای جلوگیری از تشخیص قطعی کاذب؛ مقدار ۲ نقطهٔ شروع محافظه‌کارانه‌ای است.</dd></div>
                <div><dt>نگهداری تاریخچه</dt><dd>تعداد روزهایی که رویدادها و نتایج بررسی نگهداری می‌شوند.</dd></div>
            </dl>
            <p class="admin-guide-note">فیلتر صفحه بر اساس scheme واقعی URL انجام می‌شود: فقط نشانی‌های شروع‌شونده با <code>http://</code> و <code>https://</code> وارد پایش می‌شوند. فیلد «پروتکل» در ویرایش میرور صرفاً متادیتاست و eligibility پایش را تعیین نمی‌کند. داشبورد وضعیت را در کارت‌های سازگار با موبایل و دسکتاپ نشان می‌دهد.</p>
            <p>در نوار بالای فهرست می‌توانید همهٔ نشانی‌ها، فقط HTTP یا فقط HTTPS را انتخاب کنید. آمار پاسخ، کد HTTP، آپتایم، روند پاسخ و سلامت metadata در کارت هر میرور نمایش داده می‌شود.</p>
            <p class="admin-guide-note">پایش HTTP/HTTPS به‌تنهایی صحت تمام بسته‌ها، تازگی همهٔ metadataها یا اعتبار امضاهای رمزنگاری‌شده را تضمین نمی‌کند.</p>
            <a class="btn btn-outline" href="<?= SITE_URL ?>/admin/monitor.php"><?= icon('activity', 15) ?> باز کردن تنظیمات پایش</a>
        </article>

        <article class="admin-guide-card" id="guide-categories">
            <div class="admin-guide-card-heading">
                <span class="admin-guide-number">۰۴</span>
                <div><span class="admin-guide-kicker">ساختار داده‌ها</span><h2>تفاوت دستهٔ نرم‌افزار و ارائه‌دهنده</h2></div>
            </div>
            <div class="admin-guide-compare">
                <div><strong>دستهٔ نرم‌افزار</strong><p>برای صفحه‌های کاربر و راهنماهای نصب یا تغییر مخزن استفاده می‌شود.</p></div>
                <div><strong>دستهٔ ارائه‌دهنده</strong><p>تعیین می‌کند کدام سرورها در چرخهٔ پایش قرار بگیرند.</p></div>
            </div>
            <p class="admin-guide-note">یک میرور برای ورود به چرخهٔ پایش باید فعال باشد و حداقل به یک دستهٔ ارائه‌دهنده متصل شده باشد. برای دستهٔ ارائه‌دهنده Base URL کامل HTTP یا HTTPS تنظیم کنید؛ صفحهٔ ارائه‌دهنده فقط همین schemeها را می‌پذیرد و در صورت تنظیم Base URL، میزبان، پورت و مسیر را نیز تطبیق می‌دهد. این فیلتر به دسته‌های نرم‌افزار اعمال نمی‌شود.</p>
            <h3>نشانی پایه و آیکون دسته</h3>
            <ol class="admin-guide-steps">
                <li>هنگام افزودن یا ویرایش دسته، مقدار «نشانی پایه (Base URL)» را در صورت نیاز وارد کنید؛ این URL باید کامل و با <code>http://</code> یا <code>https://</code> شروع شود.</li>
                <li>Base URL را برای دستهٔ ارائه‌دهنده تنظیم کنید؛ در صفحهٔ ارائه‌دهنده، فقط URLهای HTTP/HTTPS منطبق با scheme، میزبان، پورت و مسیر پایه نمایش داده می‌شوند. این مقدار با پروتکل ثبت‌شدهٔ میرور متفاوت است.</li>
                <li>برای آیکون، فایل SVG، PNG، JPG یا WebP تا ۵۰۰ کیلوبایت انتخاب کنید. SVG باید فقط شکل و مسیر ثابت داشته باشد؛ اسکریپت، رویداد یا منبع خارجی مجاز نیست.</li>
                <li>برای حذف آیکون فعلی از دکمهٔ جداگانهٔ «حذف آیکون فعلی» استفاده کنید؛ فرم حذف مستقل از فرم ویرایش است.</li>
            </ol>
            <div class="admin-guide-actions">
                <a class="btn btn-outline" href="<?= SITE_URL ?>/admin/categories.php">دسته‌بندی‌ها</a>
                <a class="btn btn-outline" href="<?= SITE_URL ?>/admin/mirrors.php">میرورها</a>
            </div>
        </article>

        <article class="admin-guide-card" id="guide-security">
            <div class="admin-guide-card-heading">
                <span class="admin-guide-number">۰۵</span>
                <div><span class="admin-guide-kicker">امنیت و بازیابی</span><h2>پشتیبان‌گیری و اطلاعات محرمانه</h2></div>
            </div>
            <ol class="admin-guide-steps">
                <li>پیش از تغییرات عمده یا بازیابی اطلاعات، یک نسخهٔ پشتیبان تازه تهیه کنید.</li>
                <li>مقاله‌های CMS همراه پشتیبان کامل پایگاه داده نگهداری می‌شوند.</li>
                <li>فایل‌های معمولی پوشهٔ <code>uploads/</code>، از جمله آیکون دسته‌ها، در پشتیبان نسخهٔ ۶ قرار می‌گیرند. خروجی JSON محتوای فایل‌ها را Base64 می‌کند و خروجی ZIP فایل‌ها را مستقل در کنار manifest قرار می‌دهد.</li>
                <li>برای بازیابی، فایل JSON یا ZIP را وارد کنید؛ مسیرهای خطرناک، فایل‌های اجرایی، اندازه و SHA-256 کنترل می‌شوند و ZIP بدون extract مسیرهای دلخواه پردازش می‌شود. ZIP به افزونهٔ PHP <code>ZipArchive</code> نیاز دارد.</li>
                <li>کلید Cron، توکن ربات بله و مشخصات اتصال دیتابیس را در مخزن عمومی، اسکرین‌شات یا گزارش عمومی منتشر نکنید.</li>
                <li>اگر اطلاعات محرمانه قبلاً وارد تاریخچهٔ Git شده، مقدار افشاشده را تعویض کنید؛ حذف فایل از آخرین Commit کافی نیست.</li>
            </ol>
            <a class="btn btn-outline" href="<?= SITE_URL ?>/admin/backup.php"><?= icon('database', 15) ?> مدیریت پشتیبان‌ها</a>
        </article>

        <article class="admin-guide-card" id="guide-uploads">
            <div class="admin-guide-card-heading">
                <span class="admin-guide-number">۰۷</span>
                <div><span class="admin-guide-kicker">آپلود و انتخاب فایل</span><h2>انتخاب، کشیدن و چسباندن فایل</h2></div>
            </div>
            <ol class="admin-guide-steps">
                <li>در هر ورودی فایل، روی ناحیهٔ انتخاب تمرکز کنید یا آن را لمس کنید.</li>
                <li>فایل را از سیستم روی ناحیه بکشید و رها کنید؛ پس از انتخاب، نام و اندازهٔ فایل در همان فرم نمایش داده می‌شود.</li>
                <li>برای چسباندن تصویر کپی‌شده، ناحیهٔ آپلود را متمرکز کنید و سپس <kbd>Ctrl+V</kbd> (در macOS: <kbd>⌘+V</kbd>) را بزنید؛ کد از Clipboard Files و Clipboard Items برای مرورگرهای مختلف استفاده می‌کند.</li>
                <li>نوع و اندازهٔ فایل همچنان در سرور بررسی می‌شود؛ انتخاب فایل به‌معنی پذیرش نهایی آن نیست.</li>
            </ol>
            <p class="admin-guide-note">فایل JSON یا ZIP تا ۱۰۰ مگابایت در فرم پذیرفته می‌شود؛ محدودیت واقعی هاست مانند <code>upload_max_filesize</code>، <code>post_max_size</code>، <code>memory_limit</code> و reverse proxy نیز باید کافی باشد. سقف هر فایل آپلودی ۱۰ مگابایت، مجموع ۶۴ مگابایت و تعداد ۱۰۰۰ فایل است.</p>
            <a class="btn btn-outline" href="<?= SITE_URL ?>/admin/backup.php"><?= icon('database', 15) ?> پشتیبان و فایل‌ها</a>
        </article>

        <article class="admin-guide-card" id="guide-repository">
            <div class="admin-guide-card-heading">
                <span class="admin-guide-number">۰۶</span>
                <div><span class="admin-guide-kicker">سلامت محتوا</span><h2>بررسی سلامت و قالب مخزن</h2></div>
            </div>
            <p>در صفحهٔ جزئیات هر میرور، وضعیت HTTP، تاریخچهٔ پاسخ و نتیجهٔ بررسی متادیتا جداگانه نمایش داده می‌شود. قابل‌دسترس بودن URL معادل سالم بودن محتوای مخزن نیست.</p><p class="admin-guide-note">دکمهٔ «پینگ» روی صفحهٔ اصلی، جست‌وجو، فهرست میرورها، جزئیات میرور و دسته‌ها با اسکریپت مرورگر اجرا می‌شود؛ این سنجش ICMP نیست و زمان درخواست HTTP را می‌سنجد. Mixed Content، CSP، DNS، TLS یا مسدودسازی شبکه می‌تواند آن را متوقف کند. اگر دکمه هیچ واکنشی ندارد، مطمئن شوید <code>assets/js/ping.js</code> در همان route بارگذاری شده است.</p>
            <p>برای قالب APT، APK، Pacman یا RPM، نسخهٔ توزیع و معماری هدف را با ارائه‌دهنده تطبیق دهید. جای‌نگهدارهایی مانند <code>&lt;CODENAME&gt;</code> یا <code>&lt;ALPINE_VERSION&gt;</code> باید با مقدار واقعی جایگزین شوند.</p>
            <p class="admin-guide-note">مقدار «تنظیم نشده» در سلامت متادیتا یعنی هنوز پروفایل محتوا برای آن میرور پیکربندی نشده است، نه اینکه مخزن خراب باشد.</p>
            <a class="btn btn-outline" href="<?= SITE_URL ?>/admin/mirrors.php"><?= icon('server', 15) ?> مشاهدهٔ میرورها</a>
        </article>
    </div>
</div>

<?php renderFooter(); ?>
