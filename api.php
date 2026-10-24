<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/monitoring.php';

if ((string)($_GET['api_version'] ?? '') === '1') {
    require_once 'includes/api-v1.php';
    mirrorhubApiV1($pdo);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'data' => null, 'message' => ''];

try {
    switch ($action) {
        case 'get_categories':
            $parent_id = isset($_GET['parent_id']) && $_GET['parent_id'] !== ''
                ? (int)$_GET['parent_id']
                : null;
            $categories = getCategories($pdo, $parent_id);
            $response['success'] = true;
            $response['data'] = array_map(function ($cat) {
                return [
                    'id' => (int)$cat['id'],
                    'name' => $cat['name'],
                    'slug' => $cat['slug'],
                    'parent_id' => $cat['parent_id'] !== null ? (int)$cat['parent_id'] : null,
                    'sort_order' => (int)$cat['sort_order'],
                ];
            }, $categories);
            break;

        case 'search':
            $q = sanitize($_GET['q'] ?? '');
            if (mb_strlen($q) < 2) {
                $response['success'] = true;
                $response['data'] = [];
                $response['message'] = 'عبارت جستجو باید حداقل ۲ کاراکتر باشد.';
                break;
            }
            $like = "%$q%";
            $stmt = $pdo->prepare("SELECT id, name_fa, name_en, slug, url, protocol FROM mirrors WHERE status = 'active' AND (name_fa LIKE ? OR name_en LIKE ? OR description LIKE ?) ORDER BY name_fa ASC LIMIT 10");
            $stmt->execute([$like, $like, $like]);
            $rows = $stmt->fetchAll();
            $response['success'] = true;
            $response['data'] = array_map(function ($row) {
                return [
                    'id' => (int)$row['id'],
                    'name_fa' => $row['name_fa'],
                    'name_en' => $row['name_en'],
                    'slug' => $row['slug'],
                    'url' => $row['url'],
                    'protocols' => getMirrorProtocols($pdo, $row['id']),
                ];
            }, $rows);
            break;

        case 'get_mirrors':
            $category_id = isset($_GET['category_id']) && $_GET['category_id'] !== ''
                ? (int)$_GET['category_id']
                : null;

            if ($category_id) {
                $stmt = $pdo->prepare("SELECT DISTINCT m.id, m.name_fa, m.name_en, m.slug, m.url, m.protocol, m.description
                    FROM mirrors m
                    INNER JOIN mirror_categories mc ON m.id = mc.mirror_id
                    WHERE m.status = 'active' AND mc.category_id = ?
                    ORDER BY m.name_fa ASC");
                $stmt->execute([$category_id]);
            } else {
                $stmt = $pdo->query("SELECT id, name_fa, name_en, slug, url, protocol, description FROM mirrors WHERE status = 'active' ORDER BY name_fa ASC LIMIT 50");
            }
            $rows = $stmt->fetchAll();
            $response['success'] = true;
            $response['data'] = array_map(function ($row) {
                return [
                    'id' => (int)$row['id'],
                    'name_fa' => $row['name_fa'],
                    'name_en' => $row['name_en'],
                    'slug' => $row['slug'],
                    'url' => $row['url'],
                    'protocol' => $row['protocol'],
                    'description' => $row['description'],
                ];
            }, $rows);
            break;

        case 'stats':
            $stats = $pdo->query("SELECT
                (SELECT COUNT(*) FROM mirrors WHERE status = 'active') as mirrors,
                (SELECT COUNT(*) FROM categories) as categories")->fetch();
            $visits = getVisitStats($pdo);
            $response['success'] = true;
            $response['data'] = [
                'mirrors' => (int)$stats['mirrors'],
                'categories' => (int)$stats['categories'],
                'visits_today' => $visits['today'],
                'visits_total' => $visits['total'],
            ];
            break;

        case 'monitor_summary':
            $summary = monitorStatusSummary($pdo);
            $summary['last_run_ago'] = monitorTimeAgoFa($summary['last_run_at']);
            $summary['last_run_fa'] = monitorFaDateTime($summary['last_run_at']);
            $mirrorRows = array_map(function ($row) {
                return [
                    'id'             => (int)$row['id'],
                    'status'         => $row['status'],
                    'response_ms'    => $row['last_response_ms'] !== null ? (int)$row['last_response_ms'] : null,
                    'http_code'      => $row['last_http_code'] !== null ? (int)$row['last_http_code'] : null,
                    'uptime_percent' => $row['uptime_percent'] !== null ? (float)$row['uptime_percent'] : null,
                    'down_since'     => $row['down_since'],
                    'last_checked_at'=> $row['last_checked_at'],
                    'repository_health_profile' => $row['repository_health_profile'] ?? 'http_only',
                    'repository_health_status' => $row['repository_health_status'] ?? 'not_configured',
                    'repository_health_checked_at' => $row['repository_health_checked_at'] ?? null,
                    'repository_metadata_date' => $row['repository_metadata_date'] ?? null,
                    'repository_age_seconds' => isset($row['repository_age_seconds']) ? (int)$row['repository_age_seconds'] : null,
                    'repository_health_message' => $row['repository_health_message'] ?? null,
                ];
            }, monitorMirrorRows($pdo));
            $response['success'] = true;
            $response['data'] = [
                'summary'  => $summary,
                'mirrors'  => $mirrorRows,
                'generated_at' => date('c'),
            ];
            break;

        case 'monitor_history':
            $mirrorId = (int)($_GET['mirror_id'] ?? 0);
            if ($mirrorId <= 0) {
                $response['message'] = 'mirror_id is required';
                break;
            }
            $mirror = getMirrorById($pdo, $mirrorId);
            if (!$mirror) {
                $response['message'] = 'Mirror not found';
                http_response_code(404);
                break;
            }
            $response['success'] = true;
            $response['data'] = [
                'mirror'  => ['id' => (int)$mirror['id'], 'name_fa' => $mirror['name_fa'], 'name_en' => $mirror['name_en'], 'url' => $mirror['url']],
                'latency' => monitorLatencySeries($pdo, $mirrorId, 50),
                'daily'   => array_values(monitorUptimeSeries($pdo, $mirrorId, 30)),
            ];
            break;

        default:
            $response['message'] = 'Action not found';
            http_response_code(404);
            break;
    }
} catch (Exception $e) {
    $response['message'] = 'Server error';
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);