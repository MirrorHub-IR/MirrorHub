<?php
require_once __DIR__ . '/includes/db.php';
header('Content-Type: application/xml; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc><?= htmlspecialchars(SITE_URL . '/', ENT_XML1, 'UTF-8') ?></loc></url>
  <url><loc><?= htmlspecialchars(SITE_URL . '/guides/', ENT_XML1, 'UTF-8') ?></loc></url>
<?php
$stmt = $pdo->query("SELECT slug, updated_at FROM cms_articles WHERE status='published' ORDER BY updated_at DESC");
foreach ($stmt as $row):
?>
  <url><loc><?= htmlspecialchars(SITE_URL . '/guides/' . rawurlencode($row['slug']), ENT_XML1, 'UTF-8') ?></loc><lastmod><?= gmdate('Y-m-d', strtotime($row['updated_at'])) ?></lastmod></url>
<?php endforeach; ?>
</urlset>
