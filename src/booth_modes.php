<?php
declare(strict_types=1);

/*
 * Photobooth extras: photo strips (four shots in one classic strip, first frame optionally
 * restyled by the AI) and boomerangs (a short clip played forward then backward).
 */

const STRIP_FRAMES = 4;
const STRIP_FRAME_EDGE = 1200;
const BOOMERANG_MAX_UPLOAD = 40 * 1024 * 1024;
const BOOMERANG_SECONDS = 1.8;

const BOOTH_RULES_STRIP = 'There may be one person or several. Apply the look to everyone in the photo and keep exactly the same number of people; '
    . 'do not add, remove or merge anyone. Keep every face, expression, skin tone, pose, glasses, facial hair and hairstyle clearly recognizable. '
    . 'Family-friendly and office-appropriate: no gore, nudity, weapons or text. Landscape orientation.';

function booth_font(bool $bold = false): ?string
{
    $path = '/usr/share/fonts/truetype/dejavu/DejaVuSans' . ($bold ? '-Bold' : '') . '.ttf';
    return is_readable($path) ? $path : null;
}

function booth_media_dir(array $contest): string
{
    $dir = 'booth/' . (int) $contest['id'];
    if (!is_dir(media_dir() . '/' . $dir) && !mkdir(media_dir() . '/' . $dir, 0775, true)) {
        throw new RuntimeException('The server could not save the photo.');
    }
    return $dir;
}

// ---------------------------------------------------------------- photo strip

/** Save the four strip shots. Returns [row, null] or [null, error]. */
function booth_save_strip(array $contest, array $files, string $name): array
{
    $list = [];
    foreach ((array) ($files['tmp_name'] ?? []) as $i => $tmp) {
        $list[] = ['tmp_name' => $tmp, 'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $files['size'][$i] ?? 0];
    }
    if (count($list) !== STRIP_FRAMES) {
        return [null, 'A photo strip needs ' . STRIP_FRAMES . ' shots.'];
    }
    try {
        $dir = booth_media_dir($contest);
    } catch (RuntimeException $e) {
        return [null, $e->getMessage()];
    }
    $code = new_photo_code();
    $paths = [];
    foreach ($list as $i => $file) {
        $info = ($file['error'] === UPLOAD_ERR_OK && $file['size'] <= BOOTH_MAX_UPLOAD) ? @getimagesize($file['tmp_name']) : false;
        $img = $info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            ? @imagecreatefromstring((string) file_get_contents($file['tmp_name'])) : false;
        if (!$img) {
            array_map('delete_media', $paths);
            return [null, 'One of the shots could not be read. Try again.'];
        }
        $relative = "{$dir}/{$code}-frame" . ($i + 1) . '.jpg';
        imagejpeg(image_fit($img, STRIP_FRAME_EDGE), media_dir() . '/' . $relative, 90);
        $paths[] = $relative;
    }
    db_run(
        'INSERT INTO booth_photos (code, contest_id, kind, name, original_path, frames, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$code, $contest['id'], 'strip', $name, $paths[0], json_encode($paths), 'pending', utc_now()]
    );
    return [booth_photo_find((int) db()->lastInsertId()), null];
}

/** Restyle the first frame (if a look was chosen), then build the strip. Returns the updated row. */
function booth_process_strip(array $photo, array $contest, ?array $style): array
{
    @set_time_limit(240);
    ignore_user_abort(true);
    db_run(
        "UPDATE booth_photos SET status = 'processing', style = ?, error = '', ai_runs = ai_runs + ? WHERE id = ?",
        [$style['name'] ?? 'No AI', $style ? 1 : 0, $photo['id']]
    );
    $frames = json_decode((string) $photo['frames'], true) ?: [];
    $base = dirname($photo['original_path']) . '/' . $photo['code'] . '-' . bin2hex(random_bytes(3));
    $aiFrame = null;
    try {
        $sources = array_map(fn($f) => media_dir() . '/' . $f, $frames);
        if ($style) {
            $jpeg = booth_ai_configured()
                ? openai_restyle($sources[0], $style['prompt'] . ' ' . BOOTH_RULES_STRIP, '1536x1024')
                : demo_restyle($sources[0], $style);
            $aiFrame = "{$base}-ai.jpg";
            file_put_contents(media_dir() . '/' . $aiFrame, $jpeg);
            $sources[0] = media_dir() . '/' . $aiFrame;
        }
        $relative = "{$base}.jpg";
        compose_strip($sources, $contest, media_dir() . '/' . $relative, $style['name'] ?? null);
        delete_media($photo['result_path']);
        db_run("UPDATE booth_photos SET status = 'done', result_path = ?, finished_at = ? WHERE id = ?", [$relative, utc_now(), $photo['id']]);
    } catch (Throwable $e) {
        error_log('Photobooth strip error: ' . $e->getMessage());
        db_run("UPDATE booth_photos SET status = 'failed', error = ?, finished_at = ? WHERE id = ?", [mb_substr($e->getMessage(), 0, 255), utc_now(), $photo['id']]);
    } finally {
        delete_media($aiFrame); // the AI frame lives on inside the strip
    }
    return booth_photo_find((int) $photo['id']);
}

/** Accent color for the strip frame: brand color if it's turned on, otherwise the mode's. */
function strip_accent(array $contest): array
{
    $hex = setting('use_brand_color') === '1' ? (string) setting('brand_color', '') : '';
    if (!preg_match('/^#[0-9a-f]{6}$/i', $hex)) {
        $hex = ['halloween' => '#FF7A2E', 'holiday' => '#FF5C6C', 'general' => '#3CC6D0'][$contest['mode']] ?? '#B48CF0';
    }
    return sscanf($hex, '#%02x%02x%02x');
}

/**
 * Classic photo-booth strip: logo and title on top, four frames stacked, date and company at the bottom.
 * Frames are cropped to 3:2 so every strip has the same shape.
 */
function compose_strip(array $sources, array $contest, string $outPath, ?string $lookName): void
{
    $fw = 900; $fh = 600; $pad = 54; $gap = 27; $head = 250; $foot = 150;
    $w = $fw + 2 * $pad;
    $h = $head + STRIP_FRAMES * $fh + (STRIP_FRAMES - 1) * $gap + $foot;
    $img = imagecreatetruecolor($w, $h);
    imagealphablending($img, true);
    [$ar, $ag, $ab] = strip_accent($contest);
    $bg = imagecolorallocate($img, 18, 13, 24);
    $accent = imagecolorallocate($img, $ar, $ag, $ab);
    $white = imagecolorallocate($img, 244, 238, 248);
    $muted = imagecolorallocate($img, 190, 176, 206);
    imagefill($img, 0, 0, $bg);
    imagefilledrectangle($img, 0, 0, $w, 12, $accent);
    imagefilledrectangle($img, 0, $h - 12, $w, $h, $accent);

    // Header: logo + contest title (wrapped to two lines)
    $x = $pad;
    $logoPath = setting('logo_path') ? media_dir() . '/' . setting('logo_path') : null;
    if ($logoPath && is_readable($logoPath) && ($logo = @imagecreatefrompng($logoPath))) {
        $lh = 130;
        $lw = (int) round(imagesx($logo) * $lh / imagesy($logo));
        $tile = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, $x - 8, 60 - 8, $x + $lw + 8, 60 + $lh + 8, $tile);
        imagecopyresampled($img, $logo, $x, 60, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
        $x += $lw + 40;
    }
    $bold = booth_font(true);
    $regular = booth_font();
    $title = $contest['title'];
    if ($bold) {
        $size = 44;
        $lines = strip_wrap($title, $bold, $size, $w - $pad - $x);
        while (count($lines) > 2 && $size > 26) {
            $size -= 4;
            $lines = strip_wrap($title, $bold, $size, $w - $pad - $x);
        }
        $lines = array_slice($lines, 0, 2);
        $y = count($lines) === 1 ? 145 : 115;
        foreach ($lines as $line) {
            imagettftext($img, $size, 0, $x, $y, $white, $bold, $line);
            $y += (int) ($size * 1.35);
        }
    } else {
        imagestring($img, 5, $x, 110, $title, $white);
    }

    // Frames
    $y = $head;
    foreach (array_slice($sources, 0, STRIP_FRAMES) as $src) {
        $frame = @imagecreatefromstring((string) file_get_contents($src));
        if (!$frame) {
            throw new RuntimeException('A strip frame could not be read.');
        }
        [$sw, $sh] = [imagesx($frame), imagesy($frame)];
        $scale = max($fw / $sw, $fh / $sh);
        $cw = (int) round($fw / $scale);
        $ch = (int) round($fh / $scale);
        imagecopyresampled($img, $frame, $pad, $y, (int) (($sw - $cw) / 2), (int) (($sh - $ch) / 2), $fw, $fh, $cw, $ch);
        $y += $fh + $gap;
    }

    // Footer: date, company and (if used) the AI look
    $date = (new DateTimeImmutable('now', site_tz()))->format('F j, Y');
    $left = $date . '  ·  ' . company_name();
    if ($regular) {
        imagettftext($img, 24, 0, $pad, $h - $foot + 78, $muted, $regular, $left);
        if ($lookName) {
            $box = imagettfbbox(24, 0, $bold ?? $regular, $lookName);
            imagettftext($img, 24, 0, $w - $pad - ($box[2] - $box[0]), $h - $foot + 78, $accent, $bold ?? $regular, $lookName);
        }
    } else {
        imagestring($img, 4, $pad, $h - $foot + 60, $left, $muted);
    }

    if (!imagejpeg($img, $outPath, 88)) {
        throw new RuntimeException('The server could not save the strip.');
    }
}

function strip_wrap(string $text, string $font, int $size, int $maxWidth): array
{
    $lines = [];
    $line = '';
    foreach (preg_split('/\s+/', trim($text)) as $word) {
        $try = $line === '' ? $word : "{$line} {$word}";
        $box = imagettfbbox($size, 0, $font, $try);
        if ($line !== '' && ($box[2] - $box[0]) > $maxWidth) {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $try;
        }
    }
    if ($line !== '') {
        $lines[] = $line;
    }
    return $lines;
}

// ---------------------------------------------------------------- boomerang

/**
 * Turn a short recorded clip into a boomerang: crop to portrait, play forward then backward,
 * as a small MP4 that loops. Returns [row, null] or [null, error].
 */
function booth_save_boomerang(array $contest, array $file, string $name): array
{
    $ffmpeg = ffmpeg_path();
    if (!$ffmpeg) {
        return [null, 'Boomerangs need ffmpeg on the server.'];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [null, upload_error_message((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE))];
    }
    if ($file['size'] > BOOMERANG_MAX_UPLOAD) {
        return [null, 'That clip is too large.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
    if (!str_starts_with($mime, 'video/') && $mime !== 'application/octet-stream') {
        return [null, 'The clip could not be read.'];
    }
    try {
        $dir = booth_media_dir($contest);
    } catch (RuntimeException $e) {
        return [null, $e->getMessage()];
    }
    $code = new_photo_code();
    $video = "{$dir}/{$code}-boomerang.mp4";
    $poster = "{$dir}/{$code}-poster.jpg";
    $out = media_dir() . '/' . $video;
    $t = BOOMERANG_SECONDS;
    // Centre-crop to 2:3 portrait, 720px wide, 24 fps; then forward + reverse.
    $graph = "[0:v]trim=start=0.15:duration={$t},setpts=PTS-STARTPTS,"
        . "crop='min(iw,ih*2/3)':'min(ih,iw*3/2)',scale=720:-2,fps=24,format=yuv420p,split[a][b];"
        . '[b]reverse[r];[a][r]concat=n=2:v=1[out]';
    $cmd = escapeshellarg($ffmpeg) . ' -y -hide_banner -loglevel error -i ' . escapeshellarg($file['tmp_name'])
        . ' -filter_complex ' . escapeshellarg($graph) . ' -map "[out]" -an -c:v libx264 -preset veryfast -crf 23 -movflags +faststart '
        . escapeshellarg($out) . ' 2>&1';
    exec($cmd, $output, $code1);
    if ($code1 !== 0 || !is_file($out) || filesize($out) < 1000) {
        @unlink($out);
        error_log('Boomerang ffmpeg failed: ' . implode(' ', $output));
        return [null, 'The boomerang could not be made. Try again.'];
    }
    exec(escapeshellarg($ffmpeg) . ' -y -hide_banner -loglevel error -ss 0.4 -i ' . escapeshellarg($out)
        . ' -frames:v 1 -q:v 3 ' . escapeshellarg(media_dir() . '/' . $poster) . ' 2>&1');
    db_run(
        'INSERT INTO booth_photos (code, contest_id, kind, style, name, original_path, result_path, status, created_at, finished_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$code, $contest['id'], 'boomerang', 'Boomerang', $name, is_file(media_dir() . '/' . $poster) ? $poster : $video, $video, 'done', utc_now(), utc_now()]
    );
    return [booth_photo_find((int) db()->lastInsertId()), null];
}

function booth_photo_is_video(array $photo): bool
{
    return ($photo['kind'] ?? '') === 'boomerang';
}
