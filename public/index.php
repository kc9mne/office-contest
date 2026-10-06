<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require APP_ROOT . '/src/pages/public.php';
require APP_ROOT . '/src/pages/setup.php';
require APP_ROOT . '/src/pages/admin.php';
require APP_ROOT . '/src/pages/booth.php';

csrf_check();
$path = request_path();

if (!is_installed()) {
    $path === '/setup' ? page_setup() : redirect('/setup');
    exit;
}

if ($path === '/') {
    page_home();
} elseif ($path === '/setup') {
    redirect('/');
} elseif (preg_match('#^/admin/([a-f0-9]+)(/.*)?$#', $path, $m)) {
    if (!hash_equals((string) setting('admin_token'), $m[1])) {
        abort(404);
    }
    admin_route($m[2] ?? '/');
} elseif (preg_match('#^/booth/([a-f0-9]+)(/.*)?$#', $path, $m)) {
    if (!hash_equals(booth_token(), $m[1])) {
        abort(404);
    }
    booth_route($m[2] ?? '/');
} elseif (preg_match('#^/p/([A-Z0-9]{8})$#', $path, $m)) {
    page_photo($m[1]);
} elseif (preg_match('#^/media/(.+)$#', $path, $m)) {
    page_media($m[1]);
} elseif ($path === '/gallery') {
    page_gallery();
} elseif ($path === '/videos') {
    page_videos();
} elseif ($path === '/join') {
    page_join();
} elseif ($path === '/join/done') {
    page_join_done();
} elseif ($path === '/vote') {
    page_vote();
} elseif ($path === '/session') {
    page_session();
} elseif ($path === '/qr') {
    page_qr();
} else {
    abort(404);
}
