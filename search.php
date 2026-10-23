<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/layout.php';

$query = normalizeSearchQuery($_GET['q'] ?? '');
$terms = array_values(array_filter(preg_split('/\\s+/u', $query) ?: [], static fn($term) => $term !== ''));
$terms = array_slice($terms, 0, 8);

$mirrorResults = [];
$categoryResults = [];
$guideResults = [];
$quickCategories = [];
$monitorStatuses = [];
$hasProtocolTable = hasTable($pdo, 'mirror_protocols');

if ($terms) {
    /*
     * All terms must match, but each term may match any useful mirror field.
     * Prepared statements keep the query safe; the result cap and short query cap
     * prevent large accidental searches from generating unbounded work.
     */
    $mirrorParts = [];
    $mirrorParams = [];
    foreach ($terms as $term) {
        $like = '%' . $term . '%';
        $match = '(m.name_fa LIKE ? OR m.name_en LIKE ? OR m.description LIKE ? OR m.url LIKE ? OR m.protocol LIKE ?
            OR EXISTS (
                SELECT 1 FROM mirror_categories smc
                INNER JOIN categories sc ON sc.id = smc.category_id
                LEFT JOIN categories sp ON sp.id = sc.parent_id
                WHERE smc.mirror_id = m.id
                  AND (sc.name LIKE ? OR sc.slug LIKE ? OR sc.description LIKE ? OR sp.name LIKE ?)
            )';
        array_push($mirrorParams, $like, $like, $like, $like, $like, $like, $like, $like, $like);

        if ($hasProtocolTable) {
            $match .= " OR EXISTS (
                SELECT 1 FROM mirror_protocols smp
                WHERE smp.mirror_id = m.id
                  AND (smp.protocol LIKE ? OR smp.custom_label LIKE ?)
            )";
            array_push($mirrorParams, $like, $like);
        }
        $match .= ')';
        $mirrorParts[] = $match;
    }

    $exact = $query;
    $wholeLike = '%' . $query . '%';
    $mirrorParams[] = $exact;
    $mirrorParams[] = $exact;
    $mirrorParams[] = $exact;
    $mirrorParams[] = $wholeLike;
    $mirrorParams[] = $wholeLike;
    $mirrorSql = "SELECT m.*
        FROM mirrors m
        WHERE m.status = 'active' AND (" . implode(' AND ', $mirrorParts) . ")
        ORDER BY CASE
            WHEN m.name_fa = ? OR m.name_en = ? OR m.url = ? THEN 0
            WHEN m.name_fa LIKE ? OR m.name_en LIKE ? THEN 1
            ELSE 2
        END, m.name_fa ASC
        LIMIT 30";
    $stmt = $pdo->prepare($mirrorSql);
    $stmt->execute($mirrorParams);
    $mirrorResults = $stmt->fetchAll();

    // Category landing pages are first-class search results, not just text hidden inside mirror cards.
    $categoryParts = [];
    $categoryParams = [];
    foreach ($terms as $term) {
        $like = '%' . $term . '%';
        $categoryParts[] = '(c.name LIKE ? OR c.slug LIKE ? OR c.description LIKE ? OR p.name LIKE ?)';
        array_push($categoryParams, $like, $like, $like, $like);
    }
    $categorySql = "SELECT c.id, c.name, c.slug, c.description, c.type, p.name AS parent_name
        FROM categories c
        LEFT JOIN categories p ON p.id = c.parent_id
        WHERE " . implode(' AND ', $categoryParts) . "
        ORDER BY CASE WHEN c.name = ? OR c.slug = ? THEN 0 ELSE 1 END, c.sort_order ASC, c.name ASC
        LIMIT 12";
    $categoryParams[] = $query;
    $categoryParams[] = $query;
    $stmt = $pdo->prepare($categorySql);
    $stmt->execute($categoryParams);
    $categoryResults = $stmt->fetchAll();

    // Keep guide search fast: match title and short summary; do not scan full Markdown bodies.
    $guideParts = [];
    $guideParams = [];
    foreach ($terms as $term) {
        $like = '%' . $term . '%';
        $guideParts[] = '(a.title LIKE ? OR a.excerpt LIKE ?)';
        array_push($guideParams, $like, $like);
    }
    $guideSql = "SELECT a.title, a.slug, a.excerpt, a.published_at, c.name AS category_name
        FROM cms_articles a
        LEFT JOIN categories c ON c.id = a.category_id
        WHERE a.status = 'published' AND " . implode(' AND ', $guideParts) . "
        ORDER BY CASE WHEN a.title = ? THEN 0 ELSE 1 END,
                 COALESCE(a.published_at, a.created_at) DESC, a.id DESC
        LIMIT 10";
    $guideParams[] = $query;
    $stmt = $pdo->prepare($guideSql);
    $stmt->execute($guideParams);
    $guideResults = $stmt->fetchAll();

    $mirrorIds = array_column($mirrorResults, 'id');
    $categoryMap = getMirrorCategoryTagsMap($pdo, $mirrorIds);
    $protocolMap = getMirrorProtocolsMap($pdo, $mirrorIds);
    foreach ($mirrorResults as &$mirror) {
        $id = (int)$mirror['id'];
        $mirror['categories'] = $categoryMap[$id] ?? [];
        $mirror['protocols'] = $protocolMap[$id] ?? [];
    }
    unset($mirror);
    $monitorStatuses = monitorStatusMap($pdo, $mirrorIds);
} else {
    $quickCategories = $pdo->query("
        SELECT id, name, slug, type
        FROM categories
        ORDER BY CASE WHEN type = 'software' THEN 0 ELSE 1 END, sort_order ASC, name ASC
        LIMIT 8
    ")->fetchAll();
}

$GLOBALS['pageSeo'] = [
    'title' => $query !== '' ? 'نتایج جست‌وجوی «' . $query . '» | MirrorHub' : 'جست‌وجوی میرورها | MirrorHub',
    'description' => 'جست‌وجوی میرورهای نرم‌افزاری، دسته‌بندی‌ها و راهنماها بر اساس نام، توضیحات یا آدرس.',
    'robots' => 'noindex,follow',
];
renderHeader('جست‌وجو');
?>

<section class="section search-page">
    <div class="search-intro">
        <span class="hero-kicker">جست‌وجوی یک‌جا</span>
        <h1 class="section-title">دنبال چه چیزی می‌گردید؟</h1>
        <p>نام نرم‌افزار، ارائه‌دهنده، بخشی از آدرس یا عنوان راهنما را وارد کنید.</p>
    </div>

    <form action="<?= SITE_URL ?>/search.php" method="GET" class="search-form large search-page-form" role="search" aria-label="جست‌وجوی میرورها، دسته‌بندی‌ها و راهنماها">
        <label class="visually-hidden" for="search-query">عبارت جست‌وجو</label>
        <input id="search-query" type="search" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="مثلاً Ubuntu، Debian، Alpine یا مخزن..." maxlength="120" autocomplete="off" spellcheck="false" required>
        <button type="submit">جست‌وجو</button>
        <?php if ($query !== ''): ?>
            <a class="btn btn-outline search-clear" href="<?= SITE_URL ?>/search.php">پاک‌کردن</a>
        <?php endif; ?>
    </form>
    <p class="search-hint">جست‌وجو بین نام فارسی و انگلیسی، توضیحات، آدرس میرور، دسته‌بندی‌ها و عنوان/خلاصهٔ راهنما انجام می‌شود. همهٔ واژه‌های واردشده باید با نتیجه مرتبط باشند.</p>
</section>

<?php if (!$terms): ?>
<section class="section search-suggestions">
    <h2 class="section-title">شروع سریع</h2>
    <?php if ($quickCategories): ?>
        <p class="section-desc">برای شروع لازم نیست چیزی تایپ کنید؛ یک دسته‌بندی را انتخاب کنید.</p>
        <div class="search-suggestion-list">
            <?php foreach ($quickCategories as $cat): ?>
                <a class="search-suggestion" href="<?= SITE_URL ?>/category.php?slug=<?= rawurlencode($cat['slug']) ?>">
                    <span><?= htmlspecialchars($cat['name']) ?></span>
                    <small><?= ($cat['type'] ?? '') === 'provider' ? 'ارائه‌دهنده' : 'نرم‌افزار' ?></small>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="empty-state">هنوز دسته‌بندی‌ای برای نمایش وجود ندارد.</p>
    <?php endif; ?>
</section>
<?php else: ?>
<?php $totalResults = count($mirrorResults) + count($categoryResults) + count($guideResults); ?>
<section class="section search-results-section" aria-live="polite">
    <div class="search-results-summary">
        <div>
            <h2>نتایج برای «<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>»</h2>
            <p><?= $totalResults ? number_format($totalResults, 0, '.', '٬') . ' نتیجهٔ مرتبط پیدا شد' : 'نتیجه‌ای پیدا نشد؛ عبارت کوتاه‌تر یا املای دیگری را امتحان کنید.' ?></p>
        </div>
        <?php if ($totalResults > 0): ?>
            <div class="search-result-jumps" aria-label="رفتن به گروه نتایج">
                <?php if ($mirrorResults): ?><a href="#mirror-results">میرورها <span><?= count($mirrorResults) ?></span></a><?php endif; ?>
                <?php if ($categoryResults): ?><a href="#category-results">دسته‌بندی‌ها <span><?= count($categoryResults) ?></span></a><?php endif; ?>
                <?php if ($guideResults): ?><a href="#guide-results">راهنماها <span><?= count($guideResults) ?></span></a><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$totalResults): ?>
        <div class="search-empty-state">
            <span class="search-empty-icon" aria-hidden="true"><?= icon('search', 24) ?></span>
            <h3>چیزی پیدا نشد</h3>
            <p>املای نام را بررسی کنید، یک کلمه را حذف کنید یا به‌جای نام کامل، بخشی از آدرس را بنویسید.</p>
            <a class="btn btn-primary" href="<?= SITE_URL ?>/">مرور دسته‌بندی‌ها</a>
        </div>
    <?php endif; ?>

    <?php if ($categoryResults): ?>
    <section class="search-result-group" id="category-results">
        <div class="section-header">
            <h2 class="section-title">دسته‌بندی‌ها</h2>
            <span class="badge badge-primary"><?= count($categoryResults) ?> مورد</span>
        </div>
        <div class="grid grid-cols-3">
            <?php foreach ($categoryResults as $cat): ?>
                <article class="card category-card search-category-card">
                    <a class="category-card-link" href="<?= SITE_URL ?>/category.php?slug=<?= rawurlencode($cat['slug']) ?>">
                        <span class="badge"><?= ($cat['type'] ?? '') === 'provider' ? 'ارائه‌دهنده' : 'نرم‌افزار' ?></span>
                        <h3><?= htmlspecialchars($cat['name']) ?></h3>
                        <?php if (!empty($cat['parent_name'])): ?><p class="category-parent-tag"><?= htmlspecialchars($cat['parent_name']) ?></p><?php endif; ?>
                        <?php if (!empty($cat['description'])): ?><p><?= htmlspecialchars(excerpt($cat['description'], 100)) ?></p><?php endif; ?>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($mirrorResults): ?>
    <section class="search-result-group" id="mirror-results">
        <div class="section-header">
            <h2 class="section-title">میرورها</h2>
            <span class="badge badge-primary"><?= count($mirrorResults) ?> مورد</span>
        </div>
        <div class="grid grid-cols-2">
            <?php foreach ($mirrorResults as $mirror): ?>
                <?php $names = mirrorDisplayName($mirror); ?>
                <article class="card mirror-card">
                    <div class="mirror-header">
                        <div class="mirror-titles">
                            <h3><?= htmlspecialchars($names['fa']) ?></h3>
                            <?php if ($names['en'] && $names['en'] !== $names['fa']): ?>
                                <span class="mirror-name-en" dir="ltr"><?= htmlspecialchars($names['en']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mirror-actions">
                            <a href="<?= htmlspecialchars($mirror['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-primary">بازکردن میرور</a>
                            <?= pingButton($mirror['url']) ?>
                        </div>
                    </div>
                    <?php if ($mirror['description']): ?><p class="mirror-desc"><?= htmlspecialchars($mirror['description']) ?></p><?php endif; ?>
                    <div class="mirror-meta">
                        <?= monitorCardBadge($monitorStatuses[$mirror['id']] ?? null) ?>
                        <?= protocolBadges($mirror['protocols'] ?? []) ?>
                        <?php foreach ($mirror['categories'] as $cat): ?>
                            <a href="<?= SITE_URL ?>/category.php?slug=<?= rawurlencode($cat['slug']) ?>" class="badge badge-primary"><?= htmlspecialchars($cat['name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if (count($mirrorResults) === 30): ?><p class="search-limit-note">برای حفظ سرعت، حداکثر ۳۰ میرور در این جست‌وجو نمایش داده می‌شود؛ عبارت دقیق‌تری وارد کنید تا نتایج محدودتر شوند.</p><?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($guideResults): ?>
    <section class="search-result-group" id="guide-results">
        <div class="section-header">
            <h2 class="section-title">راهنماها</h2>
            <span class="badge badge-primary"><?= count($guideResults) ?> مورد</span>
        </div>
        <div class="cms-article-grid">
            <?php foreach ($guideResults as $guide): ?>
                <article class="cms-article-card">
                    <?php if (!empty($guide['category_name'])): ?><div class="cms-article-meta"><?= htmlspecialchars($guide['category_name']) ?></div><?php endif; ?>
                    <h3><a href="<?= SITE_URL ?>/guides/<?= rawurlencode($guide['slug']) ?>"><?= htmlspecialchars($guide['title']) ?></a></h3>
                    <?php if (!empty($guide['excerpt'])): ?><p><?= htmlspecialchars($guide['excerpt']) ?></p><?php endif; ?>
                    <?php if (!empty($guide['published_at'])): ?><time><?= htmlspecialchars(formatDate($guide['published_at'])) ?></time><?php endif; ?>
                    <a class="cms-read-more" href="<?= SITE_URL ?>/guides/<?= rawurlencode($guide['slug']) ?>">مطالعهٔ راهنما ←</a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php renderFooter(); ?>
