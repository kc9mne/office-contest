<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require APP_ROOT . '/src/pages/public.php';
require APP_ROOT . '/src/pages/setup.php';
require APP_ROOT . '/src/pages/admin.php';

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
} else {
    abort(404);
}
