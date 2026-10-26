<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function mirrorhubBasePath(): string
{
    $scriptPath = parse_url((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'), PHP_URL_PATH);
    $directory = dirname(is_string($scriptPath) ? $scriptPath : '/index.php');
    if (in_array(basename($directory), ['admin', 'api', 'includes', 'cron'], true)) {
        $directory = dirname($directory);
    }
    if ($directory === '.' || $directory === '/' || $directory === '\\') {
        return '';
    }
    return rtrim(str_replace('\\', '/', $directory), '/');
}

function mirrorhubSetupUrl(): string
{
    return mirrorhubBasePath() . '/setup.php';
}

/**
 * Detect both new installations and legacy deployments created before the lock marker existed.
 * Returns installed, not_installed, incomplete or unavailable.
 */
function mirrorhubInstallationStatus(): string
{
    $markerPath = dirname(__DIR__) . '/.mirrorhub-installed';
    $configured = DB_NAME !== '' && DB_USER !== '' && DB_PASS !== '';

    if (is_file($markerPath)) {
        return $configured ? 'installed' : 'unavailable';
    }
    if (!$configured) {
        return 'not_installed';
    }

    try {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . (defined('DB_PORT') ? DB_PORT : '3306') . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $tableCheck = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $tableCheck->execute(['mirrorhub_installation']);
        if ((int)$tableCheck->fetchColumn() > 0) {
            $state = $pdo->query('SELECT status FROM mirrorhub_installation WHERE id = 1')->fetchColumn();
            if ($state === 'complete') {
                return 'installed';
            }
            if ($state === 'installing') {
                return 'incomplete';
            }
        }
        $tableCheck->execute(['users']);
        return (int)$tableCheck->fetchColumn() > 0 ? 'installed' : 'not_installed';
    } catch (Throwable $error) {
        return 'unavailable';
    }
}
