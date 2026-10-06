<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
date_default_timezone_set('UTC');

require APP_ROOT . '/src/helpers.php';
load_env(APP_ROOT . '/.env');

ini_set('display_errors', env('APP_DEBUG') === '1' ? '1' : '0');
error_reporting(E_ALL);

require APP_ROOT . '/src/db.php';
require APP_ROOT . '/src/settings.php';
require APP_ROOT . '/src/modes.php';
require APP_ROOT . '/src/contests.php';
require APP_ROOT . '/src/auth.php';
require APP_ROOT . '/src/uploads.php';
require APP_ROOT . '/src/secrets.php';
require APP_ROOT . '/src/booth.php';
require APP_ROOT . '/src/qr.php';
require APP_ROOT . '/src/entries.php';

$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('officevote');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => base_path() ?: '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; media-src 'self' blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

try {
    migrate();
} catch (PDOException $e) {
    error_log('Office Contest database error: ' . $e->getMessage());
    http_response_code(500);
    echo 'The site cannot reach its database. Check DB_DSN, DB_USER and DB_PASS in the .env file.';
    exit;
}
