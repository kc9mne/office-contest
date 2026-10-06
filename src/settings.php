<?php
declare(strict_types=1);

function settings_cache(bool $reload = false): array
{
    static $cache = null;
    if ($cache === null || $reload) {
        $cache = [];
        foreach (db_all('SELECT name, value FROM settings') as $row) {
            $cache[$row['name']] = $row['value'];
        }
    }
    return $cache;
}

function setting(string $name, ?string $default = null): ?string
{
    return settings_cache()[$name] ?? $default;
}

function set_setting(string $name, string $value): void
{
    db_run(
        'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
        [$name, $value]
    );
    settings_cache(true);
}

function is_installed(): bool
{
    return setting('admin_pin_hash') !== null && setting('admin_token') !== null;
}

function company_name(): string
{
    return setting('company_name', 'Our Office') ?? 'Our Office';
}

function logo_url(): ?string
{
    $path = setting('logo_path');
    return $path ? media_url($path) : null;
}

/** Initials shown in place of a logo until one is uploaded. */
function company_initials(): string
{
    $name = preg_replace('/\b(co|inc|llc|ltd|corp)\.?$/i', '', company_name());
    $words = preg_split('/\s+/', trim((string) $name)) ?: [];
    $letters = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $letters .= mb_strtoupper(mb_substr($word, 0, 1));
    }
    return $letters !== '' ? $letters : 'OC';
}

/** Inline CSS that swaps the mode accent for the brand color, when turned on. */
function brand_style(): string
{
    $color = setting('brand_color');
    if (setting('use_brand_color') !== '1' || !$color || !preg_match('/^#[0-9a-f]{6}$/i', $color)) {
        return '';
    }
    $n = hexdec(substr($color, 1));
    $lum = (0.299 * (($n >> 16) & 255) + 0.587 * (($n >> 8) & 255) + 0.114 * ($n & 255)) / 255;
    $fg = $lum > 0.6 ? '#1A1020' : '#FFFFFF';
    return "--accent:{$color};--accent-fg:{$fg};--accent-soft:color-mix(in srgb, {$color} 18%, var(--surface));";
}
