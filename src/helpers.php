<?php
declare(strict_types=1);

function load_env(string $file): void
{
    if (!is_readable($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? getenv($key);
    return ($value === false || $value === '') ? $default : (string) $value;
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Base path when the app lives in a subfolder (common on shared hosting). */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        // Only trust SCRIPT_NAME when it points at our front controller; some servers
        // (PHP's built-in one, for example) report the requested path there instead.
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $base = basename($script) === 'index.php' ? rtrim(dirname($script), '/') : '';
    }
    return $base;
}

function url(string $path = '/'): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** "https://host" for links people copy or scan. Uses APP_URL from .env when set. */
function site_origin(): string
{
    $configured = env('APP_URL');
    if ($configured && preg_match('#^(https?://[^/]+)#', $configured, $m)) {
        return $m[1];
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function media_url(string $relative): string
{
    return url('/media/' . ltrim($relative, '/'));
}

function media_dir(): string
{
    return APP_ROOT . '/storage/media';
}

function request_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    return '/' . trim($path, '/');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)), true, 303);
    exit;
}

function abort(int $code, string $message = 'Page not found'): never
{
    http_response_code($code);
    view('error', ['title' => $message, 'message' => $message]);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!is_post()) {
        return;
    }
    // An upload bigger than post_max_size arrives with no fields at all.
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if (!$_POST && !$_FILES && $length > 0) {
        $message = 'That file is too large for the server. Videos can be up to ' . VIDEO_MAX_MB . ' MB.';
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            json_response(['error' => $message], 413);
        }
        abort(413, $message);
    }
    $sent = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            json_response(['error' => 'Session expired.', 'csrfExpired' => true], 400);
        }
        abort(400, 'This form expired. Go back, reload the page and try again.');
    }
}

function flash(string $message): void
{
    $_SESSION['flash'] = $message;
}

function take_flash(): ?string
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function post_str(string $key, int $max = 200): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    return mb_substr($value, 0, $max);
}

function post_bool(string $key): bool
{
    return !empty($_POST[$key]);
}

/** Render a view inside the shared layout. */
function view(string $name, array $vars = [], string $layout = 'layout'): void
{
    $vars['flash'] = $vars['flash'] ?? take_flash();
    extract($vars, EXTR_SKIP);
    ob_start();
    require APP_ROOT . "/src/views/{$name}.php";
    $content = ob_get_clean();
    require APP_ROOT . "/src/views/{$layout}.php";
}

// ---- time: stored in UTC, entered and shown in the site's time zone ----

function site_tz(): DateTimeZone
{
    try {
        return new DateTimeZone(setting('timezone', 'UTC'));
    } catch (Throwable) {
        return new DateTimeZone('UTC');
    }
}

/** "2026-10-27T09:00" in the site zone -> "2026-10-27 14:00:00" UTC, or null if invalid. */
function local_input_to_utc(string $value): ?string
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, site_tz());
    if (!$dt) {
        return null;
    }
    return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function utc_to_local(string $utc, string $format = 'D M j, g:i A'): string
{
    $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    return $dt->setTimezone(site_tz())->format($format);
}

function utc_now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function utc_timestamp(string $utc): int
{
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->getTimestamp();
}
