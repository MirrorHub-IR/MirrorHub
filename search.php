<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/layout.php';

$query = sanitize($_GET['q'] ?? '');
$results = [];

if ($query) {
    $like = "%$query%";
    $stmt = $pdo->prepare("SELECT * FROM mirrors
        WHERE status = 'active'
        AND (name_fa LIKE ? OR name_en LIKE ? OR description LIKE ? OR url LIKE ?)
        ORDER BY name_fa ASC");
    $stmt->execute([$like, $like, $like, $like]);
    $results = $stmt->fetchAll();
    foreach ($results as &$m) {
        $m['categories'] = getMirrorCategoryTags($pdo, $m['id']);
        $m['protocols'] = getMirrorProtocols($pdo, $m['id']);
    }
    unset($m);
    // وضعیت لحظه‌ای پایش سرورها برای نشان دادن روی کارت‌ها
    $monitorStatuses = monitorStatusMap($pdo, array_column($results, 'id'));
}

$GLOBALS['pageSeo'] = [
    'title' => $query !== '' ? 'نتایج جست‌وجو برای ' . $query . ' | MirrorHub' : 'جست‌وجوی میرورها | MirrorHub',
    'description' => 'جست‌وجو میان میرورهای نرم‌افزاری بر اساس نام، توضیحات یا آدرس.',
    'robots' => 'noindex,follow',
];
renderHeader('جستجو');
?>

<section class="section">
    <h1 class="section-title">جستجوی میرور</h1>
    <form action="<?= SITE_URL ?>/search.php" method="GET" class="search-form large">
        <input type="text" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="نام، توضیحات یا آدرس میرور..." required autocomplete="off" autofocus>
        <button type="submit">جستجو</button>
    </form>
</section>

<?php if ($query): ?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">نتایج جستجو برای: «<?= htmlspecialchars($query) ?>»</h2>
        <span class="badge badge-primary"><?= count($results) ?> نتیجه</span>
    </div>
    <?php if (empty($results)): ?>
        <p class="empty-state">هیچ میروری با این مشخصات یافت نشد.</p>
    <?php else: ?>
        <div class="grid grid-cols-2">
            <?php foreach ($results as $mirror): ?>
                <?php $names = mirrorDisplayName($mirror); ?>
                <div class="card mirror-card">
                    <div class="mirror-header">
                        <div class="mirror-titles">
                            <h3><?= htmlspecialchars($names['fa']) ?></h3>
                            <?php if ($names['en'] && $names['en'] !== $names['fa']): ?>
                                <span class="mirror-name-en"><?= htmlspecialchars($names['en']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mirror-actions">
                            <a href="<?= htmlspecialchars($mirror['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-primary">مشاهده</a>
                            <?= pingButton($mirror['url']) ?>
                        </div>
                    </div>
                    <?php if ($mirror['description']): ?>
                        <p class="mirror-desc"><?= htmlspecialchars($mirror['description']) ?></p>
                    <?php endif; ?>
                    <div class="mirror-meta">
                        <?= monitorCardBadge($monitorStatuses[$mirror['id']] ?? null) ?>
                        <?= protocolBadges($mirror['protocols'] ?? getMirrorProtocols($pdo, $mirror['id'])) ?>
                        <?php foreach ($mirror['categories'] as $c): ?>
                            <a href="<?= SITE_URL ?>/category.php?slug=<?= htmlspecialchars($c['slug']) ?>" class="badge badge-primary"><?= htmlspecialchars($c['name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php else: ?>
<section class="section">
    <p class="empty-state">برای جستجو، عبارت مورد نظر خود را در کادر بالا وارد کنید.</p>
</section>
<?php endif; ?>

<?php renderFooter(); ?>