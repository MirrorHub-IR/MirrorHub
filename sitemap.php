<?php
require_once __DIR__ . '/includes/db.php';
header('Content-Type: application/xml; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

$baseUrl = rtrim(SITE_URL, '/');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/', ENT_XML1, 'UTF-8') ?></loc>
  </url>
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/guides/', ENT_XML1, 'UTF-8') ?></loc>
  </url>
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/status.php', ENT_XML1, 'UTF-8') ?></loc>
  </url>
<?php
// Public categories with active mirrors, child categories, or published guides are useful landing pages.
$categoryStmt = $pdo->query("
    SELECT c.slug
    FROM categories c
    WHERE EXISTS (
        SELECT 1
        FROM mirror_categories mc
        INNER JOIN mirrors m ON m.id = mc.mirror_id AND m.status = 'active'
        WHERE mc.category_id = c.id
    )
    OR EXISTS (SELECT 1 FROM categories child WHERE child.parent_id = c.id)
    OR EXISTS (
        SELECT 1 FROM cms_articles a
        WHERE a.category_id = c.id AND a.status = 'published'
    )
    ORDER BY c.sort_order ASC, c.name ASC
");
foreach ($categoryStmt as $category):
?>
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/category.php?slug=' . rawurlencode($category['slug']), ENT_XML1, 'UTF-8') ?></loc>
  </url>
<?php endforeach; ?>

<?php
$articleStmt = $pdo->query("
    SELECT slug, updated_at
    FROM cms_articles
    WHERE status = 'published'
    ORDER BY updated_at DESC
");
foreach ($articleStmt as $article):
    $updated = !empty($article['updated_at']) ? strtotime($article['updated_at']) : false;
?>
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/guides/' . rawurlencode($article['slug']), ENT_XML1, 'UTF-8') ?></loc>
    <?php if ($updated !== false): ?><lastmod><?= gmdate('Y-m-d', $updated) ?></lastmod><?php endif; ?>
  </url>
<?php endforeach; ?>
</urlset>
