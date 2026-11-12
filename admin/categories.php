<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/layout.php';
requireAdmin();

ensureCategoryBaseUrlSchema($pdo);
$action = (string)($_POST['category_action'] ?? $_GET['action'] ?? 'list');
if (!in_array($action, ['list', 'add', 'edit'], true)) $action = 'list';
$id = max(0, (int)($_POST['category_id'] ?? $_GET['id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        flash('error', 'توکن امنیتی نامعتبر است.');
        redirect(SITE_URL . '/admin/categories.php');
    }

    if (isset($_POST['req_action']) && $_POST['req_action'] === 'delete') {
        // Delete category icon if exists
        $cat = getCategoryById($pdo, $id);
        if ($cat && !empty($cat['icon'])) {
            deleteCategoryIcon($cat['icon']);
        }
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
        flash('success', 'دسته‌بندی حذف شد.');
        redirect(SITE_URL . '/admin/categories.php');
    }
    
    if (isset($_POST['req_action']) && $_POST['req_action'] === 'delete_icon') {
        $cat = getCategoryById($pdo, $id);
        if ($cat && !empty($cat['icon'])) {
            deleteCategoryIcon($cat['icon']);
            $pdo->prepare("UPDATE categories SET icon = NULL WHERE id = ?")->execute([$id]);
            flash('success', 'آیکون حذف شد.');
        }
        redirect(SITE_URL . '/admin/categories.php?action=edit&id=' . $id);
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $slug = trim((string)($_POST['slug'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $baseUrl = trim((string)($_POST['base_url'] ?? ''));
    $currentForSave = ($action === 'edit' && $id > 0) ? getCategoryById($pdo, $id) : null;
    $type = $action === 'edit' ? (string)($currentForSave['type'] ?? '') : trim((string)($_POST['type'] ?? ''));
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($action === 'edit' && !$currentForSave) {
        flash('error', 'دسته‌بندی موردنظر برای ویرایش پیدا نشد.');
        redirect(SITE_URL . '/admin/categories.php');
    }
    if (!categoryBaseUrlIsValid($baseUrl)) {
        flash('error', 'نشانی پایه باید یک URL معتبر با پروتکل http یا https باشد.');
        redirect(SITE_URL . '/admin/categories.php?action=' . ($id ? "edit&id=$id" : 'add'));
    }
    if ($parent_id !== null) {
        $parentQuery = $pdo->prepare('SELECT id, type FROM categories WHERE id = ?');
        $parentQuery->execute([$parent_id]);
        $parentRow = $parentQuery->fetch(PDO::FETCH_ASSOC);
        if (!$parentRow || $parent_id === $id || $parentRow['type'] !== $type) {
            flash('error', 'دستهٔ والد باید موجود، هم‌نوع و متفاوت از دستهٔ فعلی باشد.');
            redirect(SITE_URL . '/admin/categories.php?action=' . ($id ? "edit&id=$id" : 'add'));
        }
    }

    if (empty($name)) {
        flash('error', 'نام دسته‌بندی الزامی است.');
        redirect(SITE_URL . '/admin/categories.php?action=' . ($id ? "edit&id=$id" : 'add'));
    }
    
    // Validate type only for add action
    if ($action !== 'edit' && !in_array($type, ['software', 'provider'])) {
        flash('error', 'نوع دسته‌بندی الزامی است.');
        redirect(SITE_URL . '/admin/categories.php?action=' . ($id ? "edit&id=$id" : 'add'));
    }

    if (empty($slug)) {
        $slug = slugify($name);
    }
    
    // Handle icon upload
    $iconFilename = null;
    if (isset($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK) {
        $currentCat = $action === 'edit' && $id ? getCategoryById($pdo, $id) : null;
        $oldIcon = $currentCat['icon'] ?? null;
        
        $uploadResult = uploadCategoryIcon($_FILES['icon'], $oldIcon);
        if ($uploadResult['success']) {
            $iconFilename = $uploadResult['filename'];
        } else {
            flash('error', $uploadResult['error']);
            redirect(SITE_URL . '/admin/categories.php?action=' . ($id ? "edit&id=$id" : 'add'));
        }
    }

    try {
        if ($action === 'edit' && $id) {
            // Update without changing type (immutable)
            $stmt = $pdo->prepare("UPDATE categories SET name=?, slug=?, description=?, base_url=?, parent_id=?, sort_order=?, icon=COALESCE(?, icon) WHERE id=?");
            $stmt->execute([$name, $slug, $description, $baseUrl !== '' ? $baseUrl : null, $parent_id, $sort_order, $iconFilename, $id]);
            if ($iconFilename && !empty($currentForSave['icon'])) deleteCategoryIcon($currentForSave['icon']);
            flash('success', 'دسته‌بندی با موفقیت ویرایش شد.');
        } else {
            // Insert with type
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description, base_url, parent_id, type, icon, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $slug, $description, $baseUrl !== '' ? $baseUrl : null, $parent_id, $type, $iconFilename, $sort_order]);
            flash('success', 'دسته‌بندی با موفقیت اضافه شد.');
        }
        redirect(SITE_URL . '/admin/categories.php');
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            flash('error', 'این نامک (slug) قبلاً استفاده شده است.');
        } else {
            flash('error', 'خطا در ذخیره‌سازی: ' . $e->getMessage());
        }
        redirect(SITE_URL . '/admin/categories.php');
    }
}

$categories = $pdo->query("SELECT c.*, p.name as parent_name FROM categories c LEFT JOIN categories p ON c.parent_id = p.id ORDER BY c.sort_order ASC, c.name ASC")->fetchAll();
$currentCat = null;
if ($action === 'edit' && $id) {
    $currentCat = getCategoryById($pdo, $id);
    if (!$currentCat) {
        flash('error', 'دسته‌بندی مورد نظر یافت نشد.');
        redirect(SITE_URL . '/admin/categories.php');
    }
}

renderHeader('مدیریت دسته‌بندی‌ها', true);
?>

<section class="section">
    <div class="section-header">
        <h1 class="section-title">مدیریت دسته‌بندی‌ها</h1>
        <?php if ($action === 'add' || $action === 'edit'): ?>
            <a href="<?= SITE_URL ?>/admin/categories.php" class="btn btn-outline">بازگشت به لیست</a>
        <?php else: ?>
            <a href="<?= SITE_URL ?>/admin/categories.php?action=add" class="btn btn-primary">افزودن دسته‌بندی جدید</a>
        <?php endif; ?>
    </div>

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <div class="form-container card">
            <h2><?= $action === 'edit' ? 'ویرایش دسته‌بندی' : 'افزودن دسته‌بندی جدید' ?></h2>
            <form id="category-editor-form" action="<?= SITE_URL ?>/admin/categories.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="category_action" value="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="category_id" value="<?= (int)$id ?>">
                
                <div class="form-group">
                    <label for="name">نام دسته‌بندی <span class="required">*</span></label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($currentCat['name'] ?? '') ?>" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="slug">نامک (Slug)</label>
                    <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($currentCat['slug'] ?? '') ?>" dir="ltr" placeholder="خالی بگذارید تا خودکار ساخته شود" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="type">نوع دسته‌بندی <span class="required">*</span></label>
                    <?php if ($action === 'edit'): ?>
                        <input type="text" value="<?= htmlspecialchars(getCategoryTypeLabel($currentCat['type'] ?? '')) ?>" disabled style="background: var(--bg-tertiary); cursor: not-allowed;">
                        <small class="form-hint" style="color: var(--text-muted);">⚠️ نوع دسته‌بندی پس از ایجاد قابل تغییر نیست.</small>
                    <?php else: ?>
                        <select id="type" name="type" required>
                            <option value="">-- انتخاب کنید --</option>
                            <?php foreach (getCategoryTypes() as $typeVal => $typeLabel): ?>
                                <option value="<?= $typeVal ?>"><?= htmlspecialchars($typeLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-hint">نوع دسته‌بندی پس از ایجاد قابل تغییر نخواهد بود.</small>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="parent_id">دسته‌بندی والد</label>
                    <select id="parent_id" name="parent_id">
                        <option value="">-- بدون والد (ریشه) --</option>
                        <?php foreach ($categories as $cat): ?>
                            <?php if ($action !== 'edit' || $cat['id'] != $id): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($currentCat['parent_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="sort_order">ترتیب نمایش</label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= htmlspecialchars($currentCat['sort_order'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label for="description">توضیحات</label>
                    <textarea id="description" name="description" rows="3"><?= htmlspecialchars((string)($currentCat['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="base_url">نشانی پایه (Base URL) — اختیاری</label>
                    <input type="url" id="base_url" name="base_url" value="<?= htmlspecialchars((string)($currentCat['base_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" dir="ltr" maxlength="1000" placeholder="https://mirror.example.org/repository">
                    <small class="form-hint">برای دستهٔ ارائه‌دهنده، نشانی ریشه را کامل و با http:// یا https:// وارد کنید؛ صفحه و فیلتر ارائه‌دهنده فقط URLهای منطبق با scheme، میزبان، پورت و مسیر این Base URL را نشان می‌دهند. در دستهٔ نرم‌افزار این فیلتر اعمال نمی‌شود. این مقدار از پروتکل ثبت‌شدهٔ میرور جداست.</small>
                </div>
                <div class="form-group">
                    <label for="icon">آیکون دسته‌بندی (اختیاری)</label>
                    <?php if ($action === 'edit' && !empty($currentCat['icon'])): ?>
                        <div class="category-icon-current">
                            <img src="<?= SITE_URL ?>/uploads/category-icons/<?= rawurlencode((string)$currentCat['icon']) ?>" alt="آیکون فعلی دسته‌بندی" class="icon-upload-preview">
                            <button type="submit" form="delete-category-icon-form" class="btn btn-sm btn-danger">حذف آیکون فعلی</button>
                        </div>
                    <?php endif; ?>
                    <div class="mh-file-upload-zone" data-upload-zone>
                        <input type="file" id="icon" name="icon" accept=".svg,image/svg+xml,image/png,image/jpeg,image/webp">
                        <span class="mh-file-upload-prompt"><?= icon('image', 22) ?><strong>فایل آیکون را انتخاب کنید یا اینجا رها کنید</strong><small>امکان چسباندن تصویر با Ctrl+V نیز وجود دارد • SVG، PNG، JPG یا WebP • حداکثر ۵۰۰ کیلوبایت</small></span>
                    </div>
                    <small class="form-hint">SVGهای دارای اسکریپت، رویدادهای فعال یا ارجاع به منابع خارجی رد می‌شوند.</small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">ذخیره</button>
                    <a href="<?= SITE_URL ?>/admin/categories.php" class="btn btn-outline">انصراف</a>
                </div>
            </form>
            <?php if ($action === 'edit' && !empty($currentCat['icon'])): ?>
                <form id="delete-category-icon-form" action="<?= SITE_URL ?>/admin/categories.php" method="POST" hidden>
                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="req_action" value="delete_icon">
                    <input type="hidden" name="category_id" value="<?= (int)$id ?>">
                </form>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-container card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>نام</th>
                        <th>نامک</th>
                        <th>نوع</th>
                        <th>والد</th>
                        <th>نشانی پایه</th>
                        <th>ترتیب</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="7" class="empty-state">دسته‌بندی‌ای یافت نشد.</td></tr>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td data-label="نام">
                                    <?php if (!empty($cat['icon'])): ?>
                                        <img src="<?= SITE_URL ?>/uploads/category-icons/<?= htmlspecialchars($cat['icon']) ?>" alt="" style="width: 24px; height: 24px; object-fit: contain; vertical-align: middle; margin-left: 0.5rem;">
                                    <?php endif; ?>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </td>
                                <td data-label="نامک" class="ltr-text"><?= htmlspecialchars($cat['slug']) ?></td>
                                <td data-label="نوع"><span class="badge category-type-<?= $cat['type'] ?? 'software' ?>"><?= htmlspecialchars(getCategoryTypeLabel($cat['type'] ?? 'software')) ?></span></td>
                                <td data-label="والد"><?= $cat['parent_name'] ? htmlspecialchars($cat['parent_name']) : '<span class="badge badge-default">ریشه</span>' ?></td>
                                <td data-label="نشانی پایه"><?php if (!empty($cat['base_url'])): ?><a class="ltr-text" href="<?= htmlspecialchars($cat['base_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(excerpt($cat['base_url'], 38), ENT_QUOTES, 'UTF-8') ?></a><?php else: ?>—<?php endif; ?></td>
                                <td data-label="ترتیب"><?= (int)$cat['sort_order'] ?></td>
                                <td data-label="عملیات" class="actions">
                                    <a href="?action=edit&id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline">ویرایش</a>
                                    <form action="<?= SITE_URL ?>/admin/categories.php" method="POST" class="inline-form" onsubmit="return confirm('آیا از حذف مطمئن هستید؟');">
                                        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
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