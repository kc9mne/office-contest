<?php
declare(strict_types=1);

const LOGO_MAX_BYTES = 5 * 1024 * 1024;
const LOGO_MAX_SIZE = 512;

/**
 * Save an uploaded logo as a PNG (re-encoded, so nothing but pixels is kept).
 * Returns [relative path, null] or [null, error message].
 */
function save_logo_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [null, upload_error_message((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE))];
    }
    if ($file['size'] > LOGO_MAX_BYTES) {
        return [null, 'That logo is too large. Use an image under 5 MB.'];
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
        return [null, 'Use a PNG, JPG, WebP or GIF image for the logo.'];
    }
    $src = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
    if (!$src) {
        return [null, 'That image could not be read. Try saving it as a PNG.'];
    }

    [$w, $h] = [imagesx($src), imagesy($src)];
    $scale = min(1, LOGO_MAX_SIZE / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $dir = media_dir() . '/brand';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        return [null, 'The server could not save the logo. Check that storage/media is writable.'];
    }
    $relative = 'brand/logo-' . bin2hex(random_bytes(6)) . '.png';
    if (!imagepng($dst, media_dir() . '/' . $relative, 6)) {
        return [null, 'The server could not save the logo. Check that storage/media is writable.'];
    }
    return [$relative, null];
}

function delete_media(?string $relative): void
{
    if (!$relative || str_contains($relative, '..')) {
        return;
    }
    $path = media_dir() . '/' . $relative;
    if (is_file($path)) {
        @unlink($path);
    }
}

function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That file is larger than the server allows.',
        UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Try again.',
        UPLOAD_ERR_NO_FILE => 'Choose a file first.',
        default => 'The upload failed. Try again.',
    };
}
