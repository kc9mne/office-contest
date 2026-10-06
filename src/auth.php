<?php
declare(strict_types=1);

const LOGIN_MAX_TRIES = 5;
const LOGIN_WINDOW_MINUTES = 15;

function admin_base(): string
{
    return '/admin/' . setting('admin_token', '');
}

function is_admin(): bool
{
    return !empty($_SESSION['admin']) && ($_SESSION['admin_token'] ?? '') === setting('admin_token');
}

function login_locked(): bool
{
    $since = gmdate('Y-m-d H:i:s', time() - LOGIN_WINDOW_MINUTES * 60);
    $row = db_one('SELECT COUNT(*) AS n FROM login_attempts WHERE ip = ? AND attempted_at > ?', [client_ip(), $since]);
    return (int) $row['n'] >= LOGIN_MAX_TRIES;
}

/** Returns an error message, or null when signed in. */
function admin_login(string $pin): ?string
{
    if (login_locked()) {
        return 'Too many wrong PINs. Wait ' . LOGIN_WINDOW_MINUTES . ' minutes and try again.';
    }
    if (!password_verify($pin, (string) setting('admin_pin_hash'))) {
        db_run('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, ?)', [client_ip(), utc_now()]);
        return 'That PIN is not right.';
    }
    db_run('DELETE FROM login_attempts WHERE ip = ?', [client_ip()]);
    admin_sign_in();
    return null;
}

function admin_sign_in(): void
{
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    $_SESSION['admin_token'] = setting('admin_token');
}

function admin_sign_out(): void
{
    unset($_SESSION['admin'], $_SESSION['admin_token']);
    session_regenerate_id(true);
}

function valid_pin(string $pin): bool
{
    return (bool) preg_match('/^\d{4,12}$/', $pin);
}

function new_admin_token(): string
{
    return bin2hex(random_bytes(8));
}
