<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/layout.php';
require_once 'includes/cms.php';
$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
    $articles = $pdo->query("SELECT a.title,a.slug,a.excerpt,a.published_at,c.name AS category_name,c.slug AS category_slug FROM cms_articles a LEFT JOIN categories c ON c.id=a.category_id WHERE a.status='published' ORDER BY a.published_at DESC,a.id DESC")->fetchAll();
    $GLOBALS['pageSeo'] = [
        'title' => 'راهنماهای میرورها و مخازن نرم‌افزاری | MirrorHub',
        'description' => 'راهنماهای کاربردی برای تنظیم مخازن لینوکس، انتخاب میرورها و رفع خطاهای رایج.',
        'canonical' => rtrim(SITE_URL, '/') . '/guides/',
        'styles' => ['css/cms.css'],
        'breadcrumbs' => [
            ['name' => 'خانه', 'url' => rtrim(SITE_URL, '/') . '/'],
            ['name' => 'مستندات', 'url' => rtrim(SITE_URL, '/') . '/guides/'],
        ],
    ];
    renderHeader('مستندات');
    ?>
    <section class="cms-hero"><span class="cms-eyebrow">مستندات MirrorHub</span><h1>مستندات</h1><p>آموزش تنظیم میرورها، استفاده از مخازن و رفع خطاهای رایج.</p></section>
    <section class="section"><div class="cms-article-grid">
    <?php foreach($articles as $a): ?><article class="cms-article-card"><div class="cms-article-meta"><?php if($a['category_name']): ?><a href="<?= SITE_URL ?>/category.php?slug=<?= rawurlencode($a['category_slug']) ?>"><?= htmlspecialchars($a['category_name']) ?></a><?php endif; ?><?php if($a['published_at']): ?><time><?= htmlspecialchars(formatDate($a['published_at'])) ?></time><?php endif; ?></div><h2><a href="<?= SITE_URL ?>/guides/<?= rawurlencode($a['slug']) ?>"><?= htmlspecialchars($a['title']) ?></a></h2><p><?= htmlspecialchars((string)($a['excerpt'] ?? '')) ?></p><a class="cms-read-more" href="<?= SITE_URL ?>/guides/<?= rawurlencode($a['slug']) ?>">مطالعه مستندات ←</a></article><?php endforeach; ?>
    </div><?php if(!$articles): ?><p class="empty-state">هنوز راهنمایی منتشر نشده است.</p><?php endif; ?></section>
    <?php renderFooter(); exit;
}
$stmt=$pdo->prepare("SELECT a.*,c.name AS category_name,c.slug AS category_slug FROM cms_articles a LEFT JOIN categories c ON c.id=a.category_id WHERE a.slug=? AND a.status='published' LIMIT 1");
$stmt->execute([$slug]); $article=$stmt->fetch();
if (!$article) { http_response_code(404); $GLOBALS['pageSeo']=['title'=>'مستندات پیدا نشد | MirrorHub','description'=>'صفحهٔ موردنظر در MirrorHub پیدا نشد یا منتشر نشده است.','robots'=>'noindex,follow']; renderHeader('مستندات پیدا نشد'); echo '<section class="section"><h1>مستندات پیدا نشد</h1><p>این مستند وجود ندارد یا منتشر نشده است.</p><a href="'.htmlspecialchars(SITE_URL).'/guides/">بازگشت به مستندات</a></section>'; renderFooter(); exit; }
$articleCanonical = rtrim(SITE_URL, '/') . '/guides/' . rawurlencode($article['slug']);
$GLOBALS['pageSeo'] = [
    'title' => $article['seo_title'] ?: ($article['title'] . ' | راهنمای MirrorHub'),
    'description' => $article['seo_description'] ?: ($article['excerpt'] ?: 'راهنمای ' . $article['title'] . ' برای استفاده از میرورها در MirrorHub'),
    'canonical' => $articleCanonical,
    'og_type' => 'article',
    'published' => $article['published_at'],
    'modified' => $article['updated_at'],
    'styles' => ['css/cms.css'],
    'breadcrumbs' => array_values(array_filter([
        ['name' => 'خانه', 'url' => rtrim(SITE_URL, '/') . '/'],
        ['name' => 'مستندات', 'url' => rtrim(SITE_URL, '/') . '/guides/'],
        !empty($article['category_name']) ? ['name' => (string)$article['category_name'], 'url' => rtrim(SITE_URL, '/') . '/category.php?slug=' . rawurlencode((string)$article['category_slug'])] : null,
        ['name' => (string)$article['title'], 'url' => $articleCanonical],
    ])),
];
$bodyHtml=cmsRenderMarkdown($pdo,(string)$article['body_markdown']);
renderHeader($article['title']);
?>
<nav class="breadcrumb"><a href="<?= SITE_URL ?>/index.php">خانه</a><span class="separator">/</span><a href="<?= SITE_URL ?>/guides/">مستندات</a><?php if($article['category_name']): ?><span class="separator">/</span><a href="<?= SITE_URL ?>/category.php?slug=<?= rawurlencode($article['category_slug']) ?>"><?= htmlspecialchars($article['category_name']) ?></a><?php endif; ?></nav>
<article class="cms-document"><header class="cms-document-header"><span class="cms-eyebrow">راهنمای MirrorHub</span><h1><?= htmlspecialchars($article['title']) ?></h1><?php if($article['excerpt']): ?><p class="cms-document-excerpt"><?= htmlspecialchars($article['excerpt']) ?></p><?php endif; ?><div class="cms-document-meta"><time datetime="<?= htmlspecialchars(date('c', strtotime($article['published_at'] ?: $article['created_at'])), ENT_QUOTES, 'UTF-8') ?>">انتشار: <?= htmlspecialchars(formatDate($article['published_at'] ?: $article['created_at'])) ?></time><time datetime="<?= htmlspecialchars(date('c', strtotime($article['updated_at'])), ENT_QUOTES, 'UTF-8') ?>">آخرین ویرایش: <?= htmlspecialchars(formatDate($article['updated_at'])) ?></time></div></header><div class="cms-document-layout"><aside class="cms-toc"><strong>در این مستند</strong><nav id="cms-toc-list"></nav></aside><div class="cms-prose" id="cms-prose"><?= $bodyHtml ?></div></div></article>
<script>(function(){const root=document.getElementById('cms-prose'),toc=document.getElementById('cms-toc-list');if(!root||!toc)return;const hs=root.querySelectorAll('h1,h2,h3,h4');if(hs.length<2){toc.closest('.cms-toc').style.display='none';return;}hs.forEach((h,i)=>{if(!h.id)h.id='section-'+(i+1);const a=document.createElement('a');a.href='#'+h.id;a.textContent=h.textContent;a.className='toc-level-'+h.tagName.toLowerCase();toc.appendChild(a);});})();</script>
<?php renderFooter(); ?>
