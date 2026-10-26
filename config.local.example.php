<?php
/**
 * Copy this file to config.local.php on the server.
 * Never commit config.local.php or place real credentials in Git.
 * Environment variables (MIRRORHUB_DB_*) take precedence over this file.
 */
return [
    'DB_HOST' => 'localhost',
    'DB_PORT' => '3306',
    'DB_NAME' => 'CHANGE_ME_DATABASE_NAME',
    'DB_USER' => 'CHANGE_ME_DATABASE_USER',
    'DB_PASS' => 'CHANGE_ME_WITH_A_NEW_DATABASE_PASSWORD',
    'DB_CHARSET' => 'utf8mb4',
    'SITE_URL' => 'https://example.com',
    'ENVIRONMENT' => 'production',
    'MONITOR_CRON_KEY' => '',
];
