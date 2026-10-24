<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/layout.php';
require_once '../includes/cms.php';
requireAdmin();

$action = $_GET['action'] ?? 'list';
$id = max(0, (int)($_GET['id'] ?? 0));
$errors = [];
$article = ['title'=>'','slug'=>'','excerpt'=>'','body_markdown'=>'','seo_title'=>'','seo_description'=>'','category_id'=>'','status'=>'draft'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        flash('error', 'توکن امنیتی نامعتبر است.');
        redirect(SITE_URL . '/admin/pages.php');
    }
    $reqAction = $_POST['req_action'] ?? 'save';
    if ($reqAction === 'delete' && $id) {
        $pdo->prepare("DELETE FROM cms_articles WHERE id = ?")->execute([$id]);
        flash('success', 'مقاله حذف شد.');
        redirect(SITE_URL . '/admin/pages.php');
    }
    $article = [
        'title'=>trim((string)($_POST['title'] ?? '')),
        'slug'=>trim((string)($_POST['slug'] ?? '')),
        'excerpt'=>trim((string)($_POST['excerpt'] ?? '')),
        'body_markdown'=>(string)($_POST['body_markdown'] ?? ''),
        'seo_title'=>trim((string)($_POST['seo_title'] ?? '')),
        'seo_description'=>trim((string)($_POST['seo_description'] ?? '')),
        'category_id'=>(int)($_POST['category_id'] ?? 0) ?: null,
        'status'=>($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft'
    ];
    if ($article['title'] === '') $errors[] = 'عنوان مقاله الزامی است.';
    if (trim($article['body_markdown']) === '') $errors[] = 'متن Markdown مقاله الزامی است.';
    $article['slug'] = cmsSlug($article['slug'] !== '' ? $article['slug'] : $article['title']);
    if (!preg_match('/^[a-z0-9\x{0600}-\x{06FF}-]+$/u', $article['slug'])) $errors[] = 'نامک نامعتبر است.';
    if (mb_strlen($article['title']) > 200) $errors[] = 'عنوان حداکثر ۲۰۰ نویسه باشد.';
    if (mb_strlen($article['seo_description']) > 320) $errors[] = 'توضیحات SEO حداکثر ۳۲۰ نویسه باشد.';
    if ($article['category_id']) {
        $q=$pdo->prepare("SELECT id FROM categories WHERE id=? AND type='software'");
        $q->execute([$article['category_id']]);
        if (!$q->fetch()) $errors[] = 'دسته‌بندی نرم‌افزار معتبر نیست.';
    }
    $q=$pdo->prepare("SELECT id FROM cms_articles WHERE slug=? AND id<>?");
    $q->execute([$article['slug'],$id]);
    if ($q->fetch()) $errors[] = 'این نامک قبلاً استفاده شده است.';
    if (!$errors) {
        if ($id) {
            $q=$pdo->prepare("SELECT published_at FROM cms_articles WHERE id=?"); $q->execute([$id]); $old=$q->fetch();
            if (!$old) { flash('error','مقاله پیدا نشد.'); redirect(SITE_URL.'/admin/pages.php'); }
            $publishedAt = $article['status']==='published' ? ($old['published_at'] ?: date('Y-m-d H:i:s')) : null;
            $q=$pdo->prepare("UPDATE cms_articles SET category_id=?,title=?,slug=?,excerpt=?,body_markdown=?,seo_title=?,seo_description=?,status=?,published_at=?,updated_by=? WHERE id=?");
            $q->execute([$article['category_id'],$article['title'],$article['slug'],$article['excerpt'],$article['body_markdown'],$article['seo_title'],$article['seo_description'],$article['status'],$publishedAt,(int)($_SESSION['user_id'] ?? 0),$id]);
        } else {
            $publishedAt = $article['status']==='published' ? date('Y-m-d H:i:s') : null;
            $q=$pdo->prepare("INSERT INTO cms_articles (category_id,title,slug,excerpt,body_markdown,seo_title,seo_description,status,created_by,updated_by,published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $q->execute([$article['category_id'],$article['title'],$article['slug'],$article['excerpt'],$article['body_markdown'],$article['seo_title'],$article['seo_description'],$article['status'],(int)($_SESSION['user_id'] ?? 0),(int)($_SESSION['user_id'] ?? 0),$publishedAt]);
            $id=(int)$pdo->lastInsertId();
        }
        flash('success', $article['status']==='published' ? 'مقاله ذخیره و منتشر شد.' : 'پیش‌نویس ذخیره شد.');
        redirect(SITE_URL.'/admin/pages.php?action=edit&id='.$id);
    }
} elseif (in_array($action,['edit','delete'],true) && $id) {
    $q=$pdo->prepare("SELECT * FROM cms_articles WHERE id=?"); $q->execute([$id]); $row=$q->fetch();
    if (!$row) { flash('error','مقاله پیدا نشد.'); redirect(SITE_URL.'/admin/pages.php'); }
    $article=$row;
}
$categories=$pdo->query("SELECT id,name FROM categories WHERE type='software' ORDER BY sort_order,name")->fetchAll();
$articles=$pdo->query("SELECT a.id,a.title,a.slug,a.status,a.updated_at,c.name AS category_name FROM cms_articles a LEFT JOIN categories c ON c.id=a.category_id ORDER BY a.updated_at DESC")->fetchAll();
renderHeader('مدیریت راهنماها', true);
?>
<section class="section">
<div class="section-header"><h1 class="section-title">مدیریت راهنماها</h1><a class="btn btn-primary" href="<?= SITE_URL ?>/admin/pages.php?action=add">مقاله جدید</a></div>
<p class="category-desc">محتوای Markdown، انتشار، SEO و اتصال به دسته‌های نرم‌افزار از همین پنل مدیریت می‌شود.</p>
<?php if($errors): ?><div class="alert alert-error"><ul><?php foreach($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if(in_array($action,['add','edit'],true)): ?>
<form method="post" class="cms-editor-form">
<input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
<div class="cms-form-grid">
<label>عنوان مقاله<input name="title" maxlength="200" required value="<?= htmlspecialchars((string)$article['title']) ?>"></label>
<label>نامک URL<input name="slug" maxlength="190" value="<?= htmlspecialchars((string)$article['slug']) ?>" placeholder="alpine-repositories"></label>
<label>راهنمای مربوط به کدام دسته است؟<select name="category_id"><option value="0">بدون اتصال به دسته</option><?php foreach($categories as $cat): ?><option value="<?= (int)$cat['id'] ?>" <?= (int)($article['category_id'] ?? 0)===(int)$cat['id']?'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option><?php endforeach; ?></select><small class="form-hint">بعد از انتشار، لینک این راهنما در صفحهٔ همان دستهٔ نرم‌افزاری نمایش داده می‌شود؛ مثلاً دستهٔ Ubuntu.</small></label>
<label>وضعیت انتشار<select name="status"><option value="draft" <?= $article['status']==='draft'?'selected':'' ?>>پیش‌نویس</option><option value="published" <?= $article['status']==='published'?'selected':'' ?>>منتشرشده</option></select></label>
</div>
<label>خلاصه<textarea name="excerpt" rows="2" maxlength="500"><?= htmlspecialchars((string)($article['excerpt'] ?? '')) ?></textarea></label>
<label>متن Markdown<textarea name="body_markdown" rows="20" required class="cms-markdown-input" spellcheck="false"><?= htmlspecialchars((string)$article['body_markdown']) ?></textarea></label>
<p class="cms-help">تیتر، فهرست، لینک، نقل‌قول و کد پشتیبانی می‌شود. برای کارت سلامت زنده از خط <code>:::health alpine</code> استفاده کنید؛ alpine نامک دسته نرم‌افزار است.</p>
<div class="cms-form-grid">
<label>عنوان SEO<input name="seo_title" maxlength="200" value="<?= htmlspecialchars((string)($article['seo_title'] ?? '')) ?>"></label>
<label>توضیحات SEO<textarea name="seo_description" rows="3" maxlength="320"><?= htmlspecialchars((string)($article['seo_description'] ?? '')) ?></textarea></label>
</div>
<div class="cms-actions"><button class="btn btn-primary" type="submit" name="req_action" value="save">ذخیره مقاله</button><?php if($id && $article['status']==='published'): ?><a class="btn btn-outline" target="_blank" rel="noopener" href="<?= SITE_URL ?>/guides/<?= rawurlencode((string)$article['slug']) ?>">نمایش</a><?php endif; ?><a class="btn btn-outline" href="<?= SITE_URL ?>/admin/pages.php">بازگشت</a></div>
</form>
<?php else: ?>
<div class="table-responsive"><table class="data-table"><thead><tr><th>عنوان</th><th>دسته</th><th>وضعیت</th><th>آخرین ویرایش</th><th>عملیات</th></tr></thead><tbody>
<?php foreach($articles as $a): ?><tr><td data-label="عنوان"><?= htmlspecialchars($a['title']) ?><small class="cms-table-slug">/guides/<?= htmlspecialchars($a['slug']) ?></small></td><td data-label="دسته"><?= htmlspecialchars($a['category_name'] ?? '—') ?></td><td data-label="وضعیت"><?= $a['status']==='published'?'منتشرشده':'پیش‌نویس' ?></td><td data-label="آخرین ویرایش"><?= htmlspecialchars(formatDate($a['updated_at'], 'Y/m/d H:i')) ?></td><td data-label="عملیات" class="actions"><a class="btn btn-sm btn-outline" href="<?= SITE_URL ?>/admin/pages.php?action=edit&id=<?= (int)$a['id'] ?>">ویرایش</a> <form method="post" action="<?= SITE_URL ?>/admin/pages.php?id=<?= (int)$a['id'] ?>" class="cms-inline-form" onsubmit="return confirm('مقاله حذف شود؟')"><input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars(generateCsrfToken()) ?>"><button class="btn btn-sm btn-danger" name="req_action" value="delete">حذف</button></form><?php if($a['status']==='published'): ?> <a class="btn btn-sm btn-outline" target="_blank" rel="noopener" href="<?= SITE_URL ?>/guides/<?= rawurlencode($a['slug']) ?>">نمایش</a><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php if(!$articles): ?><p class="empty-state">هنوز مقاله‌ای نوشته نشده است.</p><?php endif; ?>
<?php endif; ?>
</section>
<?php renderFooter(); ?>
