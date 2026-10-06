<?php
// Recover admin access from the server's command line.
//
//   sudo -u www-data php bin/admin-access.php                 show the admin link
//   sudo -u www-data php bin/admin-access.php --new-pin 2468  set a new admin PIN
//   sudo -u www-data php bin/admin-access.php --new-link      make a new admin link (old one stops working)
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/src/bootstrap.php';

$args = array_slice($argv, 1);
if (!is_installed()) {
    fwrite(STDERR, "Setup hasn't been done yet. Open the site in a browser to run first-time setup.\n");
    exit(1);
}

if (($args[0] ?? '') === '--new-pin') {
    $pin = (string) ($args[1] ?? '');
    if (!valid_pin($pin)) {
        fwrite(STDERR, "Use 4 to 12 digits, for example: --new-pin 2468\n");
        exit(1);
    }
    set_setting('admin_pin_hash', password_hash($pin, PASSWORD_DEFAULT));
    db_run('DELETE FROM login_attempts');
    echo "Admin PIN changed. Wrong-PIN lockouts were cleared.\n";
} elseif (($args[0] ?? '') === '--new-link') {
    set_setting('admin_token', new_admin_token());
    echo "New admin link created. The old link no longer works.\n";
} elseif ($args) {
    fwrite(STDERR, "Unknown option. Use no option, --new-pin <digits> or --new-link.\n");
    exit(1);
}

$origin = rtrim((string) env('APP_URL', 'https://your-site'), '/');
echo 'Admin link: ' . $origin . admin_base() . "\n";
