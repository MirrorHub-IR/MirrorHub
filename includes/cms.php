<?php
/**
 * MirrorHub integrated Markdown documentation.
 * Articles share the existing database and category/monitoring data.
 */
function ensureCmsSchema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cms_articles (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        category_id INT NULL,
        title VARCHAR(200) NOT NULL,
        slug VARCHAR(190) NOT NULL UNIQUE,
        excerpt VARCHAR(500) NULL,
        body_markdown MEDIUMTEXT NOT NULL,
        seo_title VARCHAR(200) NULL,
        seo_description VARCHAR(320) NULL,
        status ENUM('draft','published') NOT NULL DEFAULT 'draft',
        created_by INT NULL,
        updated_by INT NULL,
        published_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_cms_status_published (status, published_at),
        INDEX idx_cms_category (category_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function cmsSlug(string $value): string {
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = preg_replace('/[^a-z0-9\x{0600}-\x{06FF}]+/u', '-', $value);
    return trim((string)$value, '-') ?: 'guide-' . bin2hex(random_bytes(3));
}
function cmsEsc(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function cmsInline(string $text): string {
    $text = cmsEsc($text);
    $tick = chr(96);
    $text = preg_replace_callback('~' . $tick . '([^' . $tick . ']+)' . $tick . '~', fn($m) => '<code>' . $m[1] . '</code>', $text);
    $text = preg_replace_callback('~\[([^\]]+)\]\((https?://[^\s)]+)\)~u', fn($m) => '<a href="' . $m[2] . '" target="_blank" rel="nofollow noopener noreferrer">' . $m[1] . '</a>', $text);
    $text = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $text);
    return preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/u', '<em>$1</em>', $text);
}
function cmsHealthBlock(PDO $pdo, string $slug): string {
    $q = $pdo->prepare("SELECT id,name,slug FROM categories WHERE slug=? AND type='software' LIMIT 1");
    $q->execute([$slug]); $cat = $q->fetch();
    if (!$cat) return '<aside class="cms-callout">دسته‌بندی نرم‌افزار برای کارت سلامت پیدا نشد.</aside>';
    $q = $pdo->prepare("SELECT m.name_fa,m.name_en,s.status AS health_status,s.last_response_ms AS response_time_ms
        FROM mirrors m INNER JOIN mirror_categories mc ON mc.mirror_id=m.id AND mc.category_id=?
        LEFT JOIN mirror_status s ON s.mirror_id=m.id WHERE m.status='active'
        ORDER BY CASE s.status WHEN 'up' THEN 0 WHEN 'online' THEN 0 WHEN 'slow' THEN 1 WHEN 'down' THEN 2 ELSE 3 END,m.name_fa LIMIT 12");
    $q->execute([(int)$cat['id']]); $rows = $q->fetchAll();
    $html = '<aside class="cms-health"><div class="cms-health-head"><h3>وضعیت میرورهای ' . cmsEsc((string)$cat['name']) . '</h3><a href="' . cmsEsc(SITE_URL) . '/category.php?slug=' . rawurlencode((string)$cat['slug']) . '">مشاهده همه</a></div>';
    if (!$rows) return $html . '<p>هنوز میروری برای این دسته ثبت نشده است.</p></aside>';
    foreach ($rows as $row) {
        $status = strtolower((string)($row['health_status'] ?? 'unknown'));
        $label = in_array($status,['up','online'],true) ? 'آنلاین' : ($status==='slow'?'کند':($status==='down'?'قطع':'بررسی‌نشده'));
        $class = in_array($status,['up','online'],true) ? 'good' : ($status==='slow'?'warn':($status==='down'?'bad':'unknown'));
        $html .= '<div class="cms-health-row"><strong>' . cmsEsc((string)($row['name_fa'] ?: $row['name_en'])) . '</strong><span class="cms-status ' . $class . '">' . $label . '</span>';
        if ($row['response_time_ms'] !== null) $html .= '<span class="cms-latency">' . (int)$row['response_time_ms'] . ' ms</span>';
        $html .= '</div>';
    }
    return $html . '<p class="cms-health-note">وضعیت از آخرین بررسی ذخیره‌شده در MirrorHub خوانده می‌شود و تضمین دسترسی دائمی نیست.</p></aside>';
}
/** Safe Markdown subset: raw HTML is escaped. */
function cmsRenderMarkdown(PDO $pdo, string $source): string {
    $lines = preg_split("/\r\n|\n|\r/", $source); $out=''; $para=[]; $list=null; $code=false; $codeLines=[];
    $flush = function() use (&$out,&$para) { if ($para) { $out .= '<p>' . cmsInline(implode(' ', $para)) . '</p>'; $para=[]; } };
    $close = function() use (&$out,&$list) { if ($list) { $out .= $list==='ul' ? '</ul>' : '</ol>'; $list=null; } };
    $fence = str_repeat(chr(96),3);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line),$fence)) { $flush(); $close(); if ($code) { $out .= '<pre><code>' . cmsEsc(implode("\n",$codeLines)) . '</code></pre>'; $codeLines=[]; $code=false; } else $code=true; continue; }
        if ($code) { $codeLines[]=$line; continue; }
        if (preg_match('/^:::health\s+([a-z0-9-]+)\s*$/',trim($line),$m)) { $flush(); $close(); $out .= cmsHealthBlock($pdo,$m[1]); continue; }
        if (trim($line)==='') { $flush(); $close(); continue; }
        if (preg_match('/^(#{1,4})\s+(.+)$/u',$line,$m)) { $flush(); $close(); $n=strlen($m[1]); $out .= '<h'.$n.'>'.cmsInline(trim($m[2])).'</h'.$n.'>'; continue; }
        if (preg_match('/^>\s?(.*)$/u',$line,$m)) { $flush(); $close(); $out .= '<blockquote>'.cmsInline($m[1]).'</blockquote>'; continue; }
        if (preg_match('/^\s*[-*+]\s+(.+)$/u',$line,$m)) { $flush(); if($list==='ol')$close(); if(!$list){$out.='<ul>';$list='ul';} $out.='<li>'.cmsInline($m[1]).'</li>'; continue; }
        if (preg_match('/^\s*\d+\.\s+(.+)$/u',$line,$m)) { $flush(); if($list==='ul')$close(); if(!$list){$out.='<ol>';$list='ol';} $out.='<li>'.cmsInline($m[1]).'</li>'; continue; }
        $para[]=trim($line);
    }
    if ($code) $out.='<pre><code>'.cmsEsc(implode("\n",$codeLines)).'</code></pre>';
    $flush(); $close(); return $out;
}
