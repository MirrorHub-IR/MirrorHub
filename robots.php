<?php
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

$basePath = (string)(parse_url(SITE_URL, PHP_URL_PATH) ?? '');
$prefix = rtrim($basePath, '/');
if ($prefix === '/') $prefix = '';

echo "User-agent: *\n";
echo "Allow: " . ($prefix === '' ? '/' : $prefix . '/') . "\n";
echo "Disallow: " . $prefix . "/admin/\n";
echo "Disallow: " . $prefix . "/install.php\n";
echo "Disallow: " . $prefix . "/cron.php\n";
echo "Sitemap: " . rtrim(SITE_URL, '/') . "/sitemap.xml\n";
