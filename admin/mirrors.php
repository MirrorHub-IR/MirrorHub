<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/layout.php';
requireAdmin();

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$fromRequest = (int)($_GET['from_request'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        flash('error', 'توکن امنیتی نامعتبر است.');
        redirect(SITE_URL . '/admin/mirrors.php');
    }

    if (isset($_POST['req_action']) && $_POST['req_action'] === 'delete') {
        $pdo->prepare("DELETE FROM mirrors WHERE id = ?")->execute([$id]);
        flash('success', 'میرور حذف شد.');
        redirect(SITE_URL . '/admin/mirrors.php');
    }

    $name_fa = sanitize($_POST['name_fa'] ?? '');
    $name_en = sanitize($_POST['name_en'] ?? '');
    $slug = sanitize($_POST['slug'] ?? '');
    $url = filter_var(trim($_POST['url'] ?? ''), FILTER_SANITIZE_URL);
    $protocols = normalizeProtocols($_POST['protocols'] ?? [], $_POST['custom_protocols'] ?? []);
    $protocol = $protocols[0]['protocol'] ?? 'https';
    $description = sanitize($_POST['description'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    $category_ids = array_filter(array_map('intval', $_POST['category_ids'] ?? []), fn($v) => $v > 0);
    $linkRequestId = (int)($_POST['link_request_id'] ?? 0);

    $backUrl = SITE_URL . '/admin/mirrors.php?action=' . ($id ? "edit&id=$id" : 'add') . ($linkRequestId ? "&from_request=$linkRequestId" : '');

    if (empty($name_fa) || empty($url) || empty($category_ids) || empty($protocols)) {
        flash('error', 'لطفاً فیلدهای الزامی را پر کنید و حداقل یک دسته‌بندی انتخاب کنید.');
        redirect($backUrl);
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        flash('error', 'آدرس اینترنتی وارد شده معتبر نیست.');
        redirect($backUrl);
    }

    if (empty($slug)) {
        $slug = slugify($name_en ?: $name_fa);
    }

    try {
        if ($action === 'edit' && $id) {
            $pdo->prepare("UPDATE mirrors SET name_fa=?, name_en=?, slug=?, url=?, protocol=?, description=?, status=? WHERE id=?")
                ->execute([$name_fa, $name_en, $slug, $url, $protocol, $description, $status, $id]);
            setMirrorCategories($pdo, $id, $category_ids);
            setMirrorProtocols($pdo, $id, $protocols);
            flash('success', 'میرور با موفقیت ویرایش شد.');
        } else {
            $pdo->prepare("INSERT INTO mirrors (name_fa, name_en, slug, url, protocol, description, status) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$name_fa, $name_en, $slug, $url, $protocol, $description, $status]);
            $newId = (int)$pdo->lastInsertId();
            setMirrorCategories($pdo, $newId, $category_ids);
            setMirrorProtocols($pdo, $newId, $protocols);

            if ($linkRequestId > 0) {
                $pdo->prepare("UPDATE requests SET mirror_id = ?, status='reviewed', reviewed_by=?, reviewed_at=NOW() WHERE id = ?")
                    ->execute([$newId, (int)$_SESSION['user_id'], $linkRequestId]);
                flash('success', 'میرور ساخته شد و به درخواست مرتبط متصل شد.');
            } else {
                flash('success', 'میرور با موفقیت اضافه شد.');
            }
        }
        redirect(SITE_URL . '/admin/mirrors.php');
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            flash('error', 'این نامک (slug) قبلاً استفاده شده است.');
        } else {
            flash('error', 'خطا در ذخیره‌سازی: ' . $e->getMessage());
        }
        redirect(SITE_URL . '/admin/mirrors.php');
    }
}

$mirrors = $pdo->query("SELECT * FROM mirrors ORDER BY created_at DESC")->fetchAll();
foreach ($mirrors as &$m) {
    $m['categories'] = getMirrorCategories($pdo, $m['id']);
}
unset($m);
$monitorStatuses = monitorStatusMap($pdo, array_column($mirrors, 'id'));

$categories = getAllCategories($pdo);
$currentMirror = null;
$currentCatIds = [];
if ($action === 'edit' && $id) {
    $currentMirror = getMirrorById($pdo, $id);
    if (!$currentMirror) {
        flash('error', 'میرور مورد نظر یافت نشد.');
        redirect(SITE_URL . '/admin/mirrors.php');
    }
    $currentCatIds = array_map(fn($c) => (int)$c['id'], getMirrorCategories($pdo, $id));
    $currentProtocols = getMirrorProtocols($pdo, $id);
}

$prefill = [];
$prefillRequestId = 0;
if ($action === 'add' && $fromRequest > 0) {
    $stmt = $pdo->prepare("SELECT * FROM requests WHERE id = ?");
    $stmt->execute([$fromRequest]);
    $row = $stmt->fetch();
    if ($row) {
        $prefill = $row;
        $prefillRequestId = (int)$row['id'];
    }
}

$protocolOptions = getProtocolOptions();
$currentProtocols = $currentProtocols ?? [];

renderHeader('مدیریت میرورها', true);
?>

<?php
$selectedProtocols = $currentProtocols;
if (empty($selectedProtocols) && !empty($prefill['protocol'])) {
    $selectedProtocols = normalizeProtocols([$prefill['protocol']]);
}
$selectedProtocolValues = array_map(fn($p) => $p['protocol'], $selectedProtocols);
?>

<section class="section mirror-editor-page">
    <div class="section-header mirror-editor-topbar">
        <div>
            <div class="mirror-editor-breadcrumb">
                <span>مدیریت</span>
                <span class="mirror-editor-breadcrumb-sep">/</span>
                <span>میرورها</span>
            </div>
            <h1 class="section-title"><?= $action === 'edit' ? 'ویرایش میرور' : 'افزودن میرور جدید' ?></h1>
            <p class="section-desc"><?= $action === 'edit' ? 'اطلاعات میرور را بازبینی و به‌روزرسانی کنید.' : 'یک میرور جدید را با مشخصات کامل به MirrorHub اضافه کنید.' ?></p>
        </div>
        <div class="mirror-editor-top-actions">
            <?php if ($action === 'add' || $action === 'edit'): ?>
                <a href="<?= SITE_URL ?>/admin/mirrors.php" class="btn btn-outline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
                    بازگشت به لیست
                </a>
            <?php else: ?>
                <a href="<?= SITE_URL ?>/admin/mirrors.php?action=add" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                    افزودن میرور جدید
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <div class="mirror-form-shell mirror-form-page">
            <?php if ($prefillRequestId): ?>
                <div class="mirror-request-banner">
                    <div class="mirror-request-banner-icon">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 20 9-5-9-5-9 5 9 5Z"/><path d="m3 10 9-5 9 5-9 5-9-5Z"/><path d="M3 14l9 5 9-5"/></svg>
                    </div>
                    <div>
                        <strong>ساخت از درخواست #<?= $prefillRequestId ?></strong>
                        <p>اطلاعات این فرم از درخواست کاربر پر شده و بعد از ذخیره به همان درخواست متصل می‌شود.</p>
                    </div>
                </div>
            <?php endif; ?>

            <form action="<?= SITE_URL ?>/admin/mirrors.php?action=<?= $action ?><?= $id ? '&id=' . $id : '' ?>" method="POST" class="mirror-editor-form" data-require-category novalidate>
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCsrfToken() ?>">
                <?php if ($prefillRequestId): ?>
                    <input type="hidden" name="link_request_id" value="<?= $prefillRequestId ?>">
                <?php endif; ?>

                <div class="mirror-editor-layout">
                    <div class="mirror-editor-main">
                        <section class="mirror-editor-card">
                            <div class="mirror-card-head">
                                <div class="mirror-card-step">۱</div>
                                <div>
                                    <h2>اطلاعات اصلی</h2>
                                    <p>نام و هویت میرور را مشخص کنید.</p>
                                </div>
                            </div>

                            <div class="mirror-field-grid">
                                <div class="form-group">
                                    <label for="name_fa">نام فارسی <span class="required">*</span></label>
                                    <div class="mirror-input-wrap">
                                        <input type="text" id="name_fa" name="name_fa"
                                            value="<?= htmlspecialchars($currentMirror['name_fa'] ?? $prefill['name_fa'] ?? '') ?>"
                                            required maxlength="150" autocomplete="off" placeholder="مثلاً: مخزن اصلی اوبونتو">
                                        <span class="mirror-input-state" aria-hidden="true"></span>
                                    </div>
                                    <small class="form-hint">نامی که کاربران فارسی‌زبان در سایت می‌بینند.</small>
                                </div>

                                <div class="form-group">
                                    <label for="name_en">نام انگلیسی</label>
                                    <div class="mirror-input-wrap">
                                        <input type="text" id="name_en" name="name_en"
                                            value="<?= htmlspecialchars($currentMirror['name_en'] ?? $prefill['name_en'] ?? '') ?>"
                                            dir="ltr" maxlength="150" autocomplete="off" placeholder="e.g. Ubuntu Main Mirror">
                                    </div>
                                    <small class="form-hint">اختیاری؛ برای نمایش لاتین و شناسایی دقیق‌تر.</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="slug">نامک (Slug)</label>
                                <div class="mirror-input-prefix">
                                    <span>/mirror/</span>
                                    <input type="text" id="slug" name="slug"
                                        value="<?= htmlspecialchars($currentMirror['slug'] ?? '') ?>"
                                        dir="ltr" maxlength="160" autocomplete="off" placeholder="auto-generated-slug">
                                </div>
                                <small class="form-hint">برای URL داخلی. خالی بگذارید تا خودکار ساخته شود.</small>
                            </div>
                        </section>

                        <section class="mirror-editor-card">
                            <div class="mirror-card-head">
                                <div class="mirror-card-step">۲</div>
                                <div>
                                    <h2>آدرس و پروتکل</h2>
                                    <p>مسیر دسترسی کاربران به این میرور را تعریف کنید.</p>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="url">آدرس اینترنتی <span class="required">*</span></label>
                                <div class="mirror-url-field">
                                    <div class="mirror-url-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3c2.2 2.4 3.4 5.4 3.4 9s-1.2 6.6-3.4 9c-2.2-2.4-3.4-5.4-3.4-9S9.8 5.4 12 3Z"/></svg>
                                    </div>
                                    <input type="url" id="url" name="url"
                                        value="<?= htmlspecialchars($currentMirror['url'] ?? $prefill['url'] ?? '') ?>"
                                        dir="ltr" required maxlength="1000" placeholder="https://example.com/mirror" autocomplete="off">
                                </div>
                                <small class="form-hint">آدرس کامل را با پروتکل وارد کنید؛ مانند <span dir="ltr">https://</span> یا <span dir="ltr">rsync://</span>.</small>
                            </div>

                            <div class="form-group">
                                <div class="mirror-field-label-row">
                                    <div>
                                        <label>پروتکل‌های قابل استفاده <span class="required">*</span></label>
                                        <small class="form-hint">می‌توانید هم‌زمان چند پروتکل انتخاب کنید.</small>
                                    </div>
                                    <span id="protocol-selected-count" class="mirror-count-pill">۰ انتخاب</span>
                                </div>

                                <div class="mirror-protocol-grid">
                                    <?php
                                    foreach ($protocolOptions as $val => $label):
                                        $selected = in_array($val, $selectedProtocolValues, true);
                                        $customValue = '';
                                        if ($val === 'custom' && $selected) {
                                            foreach ($selectedProtocols as $p) {
                                                if (($p['protocol'] ?? '') === 'custom') {
                                                    $customValue = $p['custom_label'] ?? '';
                                                    break;
                                                }
                                            }
                                        }
                                        $descriptions = [
                                            'https' => 'اتصال امن و استاندارد',
                                            'http' => 'اتصال ساده HTTP',
                                            'ftp' => 'انتقال فایل با FTP',
                                            'rsync' => 'همگام‌سازی سریع فایل',
                                            'custom' => 'پروتکل اختصاصی یا خاص',
                                        ];
                                    ?>
                                        <label class="mirror-protocol-card<?= $selected ? ' is-selected' : '' ?>">
                                            <input type="checkbox" name="protocols[]" value="<?= $val ?>" <?= $selected ? 'checked' : '' ?>>
                                            <span class="mirror-protocol-check" aria-hidden="true">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                            </span>
                                            <span class="mirror-protocol-main">
                                                <strong><?= htmlspecialchars($label) ?></strong>
                                                <small><?= htmlspecialchars($descriptions[$val]) ?></small>
                                            </span>
                                            <span class="mirror-protocol-code" dir="ltr"><?= $val === 'custom' ? 'CUSTOM' : strtoupper($val) ?></span>
                                        </label>

                                        <?php if ($val === 'custom'): ?>
                                            <div id="custom-protocol-panel" class="mirror-custom-panel<?= $selected ? ' is-visible' : '' ?>">
                                                <label for="custom_protocol" class="sr-only">نام پروتکل سفارشی</label>
                                                <input class="custom-protocol-input" id="custom_protocol" type="text" name="custom_protocols[]" maxlength="100"
                                                    placeholder="مثلاً: HTTP/2 یا BitTorrent"
                                                    value="<?= htmlspecialchars($customValue) ?>" <?= $selected ? '' : 'disabled' ?>>
                                                <span>نام نمایشی پروتکل سفارشی را وارد کنید.</span>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </section>

                        <section class="mirror-editor-card">
                            <div class="mirror-card-head">
                                <div class="mirror-card-step">۳</div>
                                <div>
                                    <h2>دسته‌بندی و وضعیت</h2>
                                    <p>میرور را در بخش مناسب قرار دهید و وضعیت انتشار را تعیین کنید.</p>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="mirror-field-label-row">
                                    <div>
                                        <label>دسته‌بندی‌ها <span class="required">*</span></label>
                                        <small class="form-hint">حداقل یک دسته‌بندی انتخاب کنید.</small>
                                    </div>
                                    <span id="category-selected-count" class="mirror-count-pill">۰ انتخاب</span>
                                </div>

                                <div class="mirror-category-grid">
                                    <?php if (empty($categories)): ?>
                                        <div class="mirror-empty-picker">
                                            <strong>هنوز دسته‌بندی‌ای ساخته نشده است.</strong>
                                            <span>ابتدا از بخش دسته‌بندی‌ها یک گزینه اضافه کنید.</span>
                                        </div>
                                    <?php else: ?>
                                        <?php
                                        $parentNames = [];
                                        foreach ($categories as $cat) {
                                            if (empty($cat['parent_id'])) {
                                                $parentNames[(int)$cat['id']] = $cat['name'];
                                            }
                                        }
                                        // Group categories by type for display
                                        $catsByType = ['software' => [], 'provider' => []];
                                        foreach ($categories as $cat) {
                                            $t = $cat['type'] ?? 'software';
                                            $catsByType[$t][] = $cat;
                                        }
                                        $typeLabels = ['software' => 'نرم‌افزارها', 'provider' => 'ارائه‌دهندگان'];
                                        foreach ($catsByType as $typeKey => $typeCats):
                                            if (empty($typeCats)) continue;
                                        ?>
                                            <div class="mirror-category-group-label"><?= $typeLabels[$typeKey] ?></div>
                                        <?php
                                            foreach ($typeCats as $cat):
                                                $catId = (int)$cat['id'];
                                                $isSelected = in_array($catId, $currentCatIds, true);
                                                $parentLabel = !empty($cat['parent_id']) ? ($parentNames[(int)$cat['parent_id']] ?? 'دسته‌بندی') : null;
                                        ?>
                                            <label class="mirror-category-card<?= $isSelected ? ' is-selected' : '' ?>">
                                                <input type="checkbox" name="category_ids[]" value="<?= $catId ?>" <?= $isSelected ? 'checked' : '' ?>>
                                                <span class="mirror-category-check" aria-hidden="true">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                                </span>
                                                <span class="mirror-category-copy">
                                                    <?php if (!empty($cat['icon'])): ?>
                                                        <img src="<?= SITE_URL ?>/uploads/category-icons/<?= htmlspecialchars($cat['icon']) ?>" alt="" style="width:18px;height:18px;object-fit:contain;vertical-align:middle;margin-left:4px;">
                                                    <?php endif; ?>
                                                    <strong><?= htmlspecialchars($cat['name']) ?></strong>
                                                    <?php if ($parentLabel): ?><small><?= htmlspecialchars($parentLabel) ?></small><?php else: ?><small>دسته‌بندی اصلی</small><?php endif; ?>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mirror-status-box">
                                <div class="mirror-status-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                </div>
                                <div class="mirror-status-copy">
                                    <strong>وضعیت نمایش میرور</strong>
                                    <span>میرور غیرفعال در بخش عمومی نمایش داده نمی‌شود.</span>
                                </div>
                                <select id="status" name="status" aria-label="وضعیت میرور">
                                    <option value="active" <?= ($currentMirror['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>فعال</option>
                                    <option value="inactive" <?= ($currentMirror['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>غیرفعال</option>
                                </select>
                            </div>
                        </section>

                        <section class="mirror-editor-card">
                            <div class="mirror-card-head">
                                <div class="mirror-card-step">۴</div>
                                <div>
                                    <h2>توضیحات</h2>
                                    <p>اطلاعات تکمیلی که به شناخت بهتر میرور کمک می‌کند.</p>
                                </div>
                            </div>

                            <div class="form-group mirror-description-group">
                                <label for="description">توضیحات</label>
                                <textarea id="description" name="description" rows="5" maxlength="1000" placeholder="مثلاً این میرور برای دریافت سریع‌تر بسته‌های Ubuntu در نظر گرفته شده است..."><?= htmlspecialchars($currentMirror['description'] ?? $prefill['description'] ?? '') ?></textarea>
                                <div class="field-counter"><span id="description-count">۰</span> / ۱۰۰۰</div>
                            </div>
                        </section>

                        <div class="mirror-submit-bar">
                            <div class="mirror-submit-checks" aria-label="وضعیت فرم">
                                <span class="mirror-submit-check"><span></span> نام</span>
                                <span class="mirror-submit-check"><span></span> آدرس</span>
                                <span class="mirror-submit-check"><span></span> پروتکل</span>
                                <span class="mirror-submit-check"><span></span> دسته‌بندی</span>
                            </div>
                            <div class="mirror-submit-actions">
                                <a href="<?= SITE_URL ?>/admin/mirrors.php" class="btn btn-outline">انصراف</a>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>
                                    <?= $action === 'edit' ? 'ذخیره تغییرات' : 'ایجاد میرور' ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <aside class="mirror-editor-side">
                        <div class="mirror-preview-card">
                            <div class="mirror-preview-top">
                                <span class="mirror-preview-label">پیش‌نمایش زنده</span>
                                <span id="preview-status" class="badge badge-success">فعال</span>
                            </div>
                            <div class="mirror-preview-icon">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3c2.2 2.4 3.4 5.4 3.4 9s-1.2 6.6-3.4 9c-2.2-2.4-3.4-5.4-3.4-9S9.8 5.4 12 3Z"/></svg>
                            </div>
                            <h3 id="preview-name">نام میرور</h3>
                            <div id="preview-name-en" class="mirror-preview-name-en">Mirror Name</div>
                            <div class="mirror-preview-url" dir="ltr">
                                <span class="mirror-preview-url-dot"></span>
                                <span id="preview-url">https://example.com/mirror</span>
                            </div>
                            <div id="preview-protocols" class="mirror-preview-protocols">
                                <span class="badge badge-default">پروتکلی انتخاب نشده</span>
                            </div>
                            <p id="preview-description" class="mirror-preview-description">توضیحات میرور در اینجا نمایش داده می‌شود.</p>
                        </div>

                        <div class="mirror-checklist-card">
                            <div class="mirror-checklist-head">
                                <span class="mirror-checklist-icon">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 11 3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                                </span>
                                <div>
                                    <strong>چک‌لیست انتشار</strong>
                                    <span>قبل از ذخیره، این موارد کامل شوند.</span>
                                </div>
                            </div>
                            <div class="mirror-checklist-items">
                                <div id="check-name" class="mirror-check-item"><span></span> نام فارسی وارد شده</div>
                                <div id="check-url" class="mirror-check-item"><span></span> آدرس معتبر است</div>
                                <div id="check-protocol" class="mirror-check-item"><span></span> پروتکل انتخاب شده</div>
                                <div id="check-category" class="mirror-check-item"><span></span> حداقل یک دسته انتخاب شده</div>
                            </div>
                        </div>

                        <div class="mirror-tips-card">
                            <strong>نکته</strong>
                            <p>برای دسترسی بهتر کاربران، آدرس نهایی و پروتکل‌های واقعی سرویس را وارد کنید. پیش‌نمایش سمت چپ با هر تغییر به‌صورت زنده به‌روزرسانی می‌شود.</p>
                        </div>
                    </aside>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="table-container card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>نام</th>
                        <th>آدرس</th>
                        <th>پروتکل</th>
                        <th>دسته‌بندی‌ها</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mirrors)): ?>
                        <tr><td colspan="6" class="empty-state">میروری یافت نشد.</td></tr>
                    <?php else: ?>
                        <?php foreach ($mirrors as $mirror): ?>
                            <?php $names = mirrorDisplayName($mirror); ?>
                            <tr>
                                <td data-label="نام">
                                    <?= htmlspecialchars($names['fa']) ?>
                                    <?php if ($names['en'] && $names['en'] !== $names['fa']): ?>
                                        <br><span class="ltr-text" style="font-size:0.75rem;"><?= htmlspecialchars($names['en']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="آدرس" class="ltr-text"><?= htmlspecialchars(excerpt($mirror['url'], 40)) ?></td>
                                <td data-label="پروتکل">
                                    <?= protocolBadges($mirror['protocols'] ?? getMirrorProtocols($pdo, $mirror['id'])) ?>
                                </td>
                                <td data-label="دسته‌بندی‌ها">
                                    <?php if (empty($mirror['categories'])): ?>
                                        <span class="badge badge-default">-</span>
                                    <?php else: ?>
                                        <?php foreach ($mirror['categories'] as $c): ?>
                                            <span class="badge"><?= htmlspecialchars($c['name']) ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td data-label="وضعیت"><span class="badge badge-<?= $mirror['status'] === 'active' ? 'success' : 'warning' ?>"><?= $mirror['status'] === 'active' ? 'فعال' : 'غیرفعال' ?></span>
                                    <?= monitorCardBadge($monitorStatuses[$mirror['id']] ?? null) ?>
                                </td>
                                <td data-label="عملیات" class="actions">
                                    <a href="<?= htmlspecialchars($mirror['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline">مشاهده</a>
                                    <a href="?action=edit&id=<?= $mirror['id'] ?>" class="btn btn-sm btn-outline">ویرایش</a>
                                    <form action="?id=<?= $mirror['id'] ?>" method="POST" class="inline-form" data-confirm="آیا از حذف این میرور مطمئن هستید؟">
                                        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCsrfToken() ?>">
                                        <input type="hidden" name="req_action" value="delete">
                                        <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php renderFooter(); ?>