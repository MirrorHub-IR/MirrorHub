<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/donate.php';
require_once __DIR__ . '/../includes/layout.php';

requireAdmin();
ensureDonateSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['donate_action'] ?? '') === 'save') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        flash('error', 'توکن امنیتی نامعتبر است. دوباره تلاش کنید.');
        redirect(SITE_URL . '/admin/donate.php');
    }

    $values = [];
    $errors = [];
    $textFields = [
        'page_title' => ['عنوان اصلی صفحه', 120],
        'page_subtitle' => ['زیرعنوان صفحه', 220],
        'page_intro' => ['متن معرفی', 1200],
    ];
    foreach ($textFields as $key => [$label, $limit]) {
        $value = trim((string)($_POST[$key] ?? ''));
        if (mb_strlen($value, 'UTF-8') > $limit) $errors[] = $label . ' نباید بیشتر از ' . $limit . ' نویسه باشد.';
        elseif ($value === '') $errors[] = $label . ' نمی‌تواند خالی باشد.';
        else $values[$key] = $value;
    }

    foreach (['telegram_url' => 'لینک تلگرام', 'bale_url' => 'لینک بله', 'github_url' => 'لینک GitHub'] as $key => $label) {
        $url = trim((string)($_POST[$key] ?? ''));
        if (!donateValidateUrl($url)) $errors[] = $label . ' باید یک آدرس HTTPS معتبر باشد.';
        else $values[$key] = $url;
    }

    for ($i = 1; $i <= 4; $i++) {
        $name = trim((string)($_POST['channel_' . $i . '_name'] ?? ''));
        $url = trim((string)($_POST['channel_' . $i . '_url'] ?? ''));
        $description = trim((string)($_POST['channel_' . $i . '_description'] ?? ''));
        if (mb_strlen($name, 'UTF-8') > 50 || mb_strlen($description, 'UTF-8') > 180) {
            $errors[] = 'نام راه ارتباطی حداکثر ۵۰ و توضیح آن حداکثر ۱۸۰ نویسه باشد.';
        }
        if ($url !== '' && !donateValidateUrl($url)) {
            $errors[] = 'آدرس راه ارتباطی «' . ($name !== '' ? $name : (string)$i) . '» معتبر نیست؛ فقط HTTPS یا ایمیل پذیرفته می‌شود.';
        }
        if ($url !== '' && $name === '') $errors[] = 'برای هر لینک اضافی، یک نام نمایشی وارد کنید.';
        $values['channel_' . $i . '_name'] = $name;
        $values['channel_' . $i . '_url'] = $url;
        $values['channel_' . $i . '_description'] = $description;
    }

    if ($errors) {
        flash('error', implode(' ', $errors));
        redirect(SITE_URL . '/admin/donate.php');
    }

    try {
        donateSaveSettings($pdo, $values);
        flash('success', 'تنظیمات صفحهٔ Donate با موفقیت ذخیره شد.');
    } catch (Throwable $e) {
        error_log('MirrorHub donate settings save failed: ' . $e->getMessage());
        flash('error', 'ذخیرهٔ تنظیمات انجام نشد. اتصال پایگاه داده را بررسی کنید.');
    }
    redirect(SITE_URL . '/admin/donate.php');
}

$settings = donateSettings($pdo);
$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
renderHeader('تنظیمات صفحهٔ Donate', true);
?>

<div class="admin-donate-page">
    <section class="admin-donate-hero">
        <div>
            <span class="admin-donate-eyebrow"><?= icon('heart', 15) ?> ارتباط با جامعه</span>
            <h1>صفحهٔ Donate</h1>
            <p>متن صفحهٔ عمومی و راه‌های ارتباطی MirrorHub را از یک جا مدیریت کنید. لینک‌های تلگرام، بله و GitHub را تنظیم کنید؛ چهار کانال دلخواه دیگر هم قابل اضافه‌کردن است.</p>
        </div>
        <a class="btn btn-outline" href="<?= SITE_URL ?>/donate.php" target="_blank" rel="noopener noreferrer"><?= icon('activity', 15) ?> مشاهدهٔ صفحهٔ عمومی</a>
    </section>

    <form method="POST" class="admin-donate-form">
        <input type="hidden" name="<?= htmlspecialchars(CSRF_TOKEN_NAME, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="donate_action" value="save">

        <section class="admin-donate-panel">
            <div class="admin-donate-panel-heading">
                <span class="admin-donate-panel-icon"><?= icon('activity', 19) ?></span>
                <div><h2>محتوای صفحه</h2><p>پیام اصلی‌ای که بازدیدکننده‌ها در صفحهٔ Donate می‌بینند.</p></div>
            </div>
            <div class="form-group">
                <label for="page_title">عنوان اصلی</label>
                <input id="page_title" name="page_title" maxlength="120" required value="<?= $h($settings['page_title']) ?>">
            </div>
            <div class="form-group">
                <label for="page_subtitle">زیرعنوان</label>
                <input id="page_subtitle" name="page_subtitle" maxlength="220" required value="<?= $h($settings['page_subtitle']) ?>">
            </div>
            <div class="form-group">
                <label for="page_intro">متن معرفی</label>
                <textarea id="page_intro" name="page_intro" rows="5" maxlength="1200" required><?= $h($settings['page_intro']) ?></textarea>
                <span class="form-hint">حداکثر ۱۲۰۰ نویسه. متن ساده است و به‌صورت امن نمایش داده می‌شود.</span>
            </div>
        </section>

        <section class="admin-donate-panel">
            <div class="admin-donate-panel-heading">
                <span class="admin-donate-panel-icon telegram"><?= icon('users', 19) ?></span>
                <div><h2>راه‌های ارتباطی اصلی</h2><p>لینک‌های تلگرام، بله و GitHub در صورت تنظیم آدرس در صفحهٔ عمومی نمایش داده می‌شوند؛ آدرس بله را در این بخش وارد کنید.</p></div>
            </div>
            <div class="editor-grid">
                <div class="form-group">
                    <label for="telegram_url">لینک تلگرام</label>
                    <input type="url" id="telegram_url" name="telegram_url" dir="ltr" maxlength="500" value="<?= $h($settings['telegram_url']) ?>" placeholder="https://t.me/your_channel">
                    <span class="form-hint">کانال، گروه یا آیدی عمومی تلگرام؛ لینک را با https:// وارد کنید.</span>
                </div>
                <div class="form-group">
                    <label for="bale_url">لینک بله</label>
                    <input type="url" id="bale_url" name="bale_url" dir="ltr" maxlength="500" value="<?= $h($settings['bale_url']) ?>" placeholder="https://ble.ir/your_channel">
                    <span class="form-hint">لینک عمومی کانال یا صفحهٔ MirrorHub در بله؛ با https:// وارد کنید.</span>
                </div>
                <div class="form-group">
                    <label for="github_url">لینک GitHub</label>
                    <input type="url" id="github_url" name="github_url" dir="ltr" maxlength="500" value="<?= $h($settings['github_url']) ?>" placeholder="https://github.com/your-org/your-project">
                    <span class="form-hint">مخزن، سازمان یا صفحهٔ پروژه در GitHub.</span>
                </div>
            </div>
        </section>

        <section class="admin-donate-panel">
            <div class="admin-donate-panel-heading">
                <span class="admin-donate-panel-icon"><?= icon('plus-circle', 19) ?></span>
                <div><h2>راه‌های ارتباطی بیشتر</h2><p>تا چهار پیام‌رسان یا روش تماس دیگر مثل Discord، WhatsApp، ایمیل یا وب‌سایت اضافه کنید. ردیف‌های خالی در صفحهٔ عمومی نمایش داده نمی‌شوند.</p></div>
            </div>
            <div class="admin-donate-channels">
                <?php for ($i = 1; $i <= 4; $i++): ?>
                <fieldset class="admin-donate-channel">
                    <legend>راه ارتباطی <?= number_format($i, 0, '.', '٬') ?></legend>
                    <div class="editor-grid">
                        <div class="form-group">
                            <label for="channel_<?= $i ?>_name">نام نمایشی</label>
                            <input id="channel_<?= $i ?>_name" name="channel_<?= $i ?>_name" maxlength="50" value="<?= $h($settings['channel_' . $i . '_name']) ?>" placeholder="مثلاً Discord">
                        </div>
                        <div class="form-group">
                            <label for="channel_<?= $i ?>_url">لینک</label>
                            <input type="text" id="channel_<?= $i ?>_url" name="channel_<?= $i ?>_url" dir="ltr" maxlength="500" value="<?= $h($settings['channel_' . $i . '_url']) ?>" placeholder="https://... یا mailto:hello@example.com">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="channel_<?= $i ?>_description">توضیح کوتاه (اختیاری)</label>
                        <input id="channel_<?= $i ?>_description" name="channel_<?= $i ?>_description" maxlength="180" value="<?= $h($settings['channel_' . $i . '_description']) ?>" placeholder="برای گفتگو و دریافت خبرهای پروژه">
                    </div>
                </fieldset>
                <?php endfor; ?>
            </div>
        </section>

        <div class="admin-donate-actions">
            <span><?= icon('info', 15) ?> تغییرات پس از ذخیره بلافاصله در صفحهٔ عمومی اعمال می‌شوند.</span>
            <button type="submit" class="btn btn-primary"><?= icon('check', 16) ?> ذخیرهٔ تنظیمات</button>
        </div>
    </form>
</div>

<?php renderFooter(); ?>
