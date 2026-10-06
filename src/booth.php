<?php
declare(strict_types=1);

const BOOTH_MAX_UPLOAD = 12 * 1024 * 1024;
const BOOTH_MAX_EDGE = 1536;
const BOOTH_MAX_STYLES = 4;

/** Every AI request gets this appended, whatever the admin typed for the look. */
const BOOTH_PROMPT_RULES = 'Keep the person\'s face, expression, skin tone and pose clearly recognizable as the same person. '
    . 'Family-friendly and office-appropriate: no gore, nudity, weapons or text. Portrait orientation, the person centered.';

/** Secret part of the photobooth link, created the first time it's needed. */
function booth_token(): string
{
    $token = setting('booth_token');
    if (!$token) {
        $token = bin2hex(random_bytes(8));
        set_setting('booth_token', $token);
    }
    return $token;
}

function booth_base(): string
{
    return '/booth/' . booth_token();
}

function booth_ai_configured(): bool
{
    return env('OPENAI_API_KEY') !== null;
}

/** Looks for a contest: its own list, or the mode's defaults. */
function contest_booth_styles(array $contest): array
{
    $defaults = mode($contest['mode'])['booth_styles'];
    $saved = json_decode((string) ($contest['booth_styles'] ?? ''), true);
    if (!is_array($saved) || !$saved) {
        return $defaults;
    }
    $styles = [];
    foreach (array_values($saved) as $i => $s) {
        if (!is_array($s) || trim((string) ($s['name'] ?? '')) === '' || trim((string) ($s['prompt'] ?? '')) === '') {
            continue;
        }
        $fallback = $defaults[$i % count($defaults)];
        $styles[] = [
            'name' => (string) $s['name'],
            'prompt' => (string) $s['prompt'],
            'from' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($s['from'] ?? '')) ? $s['from'] : $fallback['from'],
            'to' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($s['to'] ?? '')) ? $s['to'] : $fallback['to'],
        ];
    }
    return $styles ?: $defaults;
}

function booth_style_by_name(array $contest, string $name): ?array
{
    foreach (contest_booth_styles($contest) as $style) {
        if ($style['name'] === $name) {
            return $style;
        }
    }
    return null;
}

/** AI runs so far today (site time zone), across all contests. */
function booth_runs_today(): int
{
    $start = (new DateTimeImmutable('today', site_tz()))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $row = db_one('SELECT COALESCE(SUM(ai_runs), 0) AS n FROM booth_photos WHERE created_at >= ?', [$start]);
    return (int) $row['n'];
}

function booth_limit_reached(array $contest): bool
{
    $limit = (int) $contest['booth_daily_limit'];
    return $limit > 0 && booth_runs_today() >= $limit;
}

function booth_photo_find(int $id): ?array
{
    return db_one('SELECT * FROM booth_photos WHERE id = ?', [$id]);
}

function booth_photo_by_code(string $code): ?array
{
    return db_one('SELECT * FROM booth_photos WHERE code = ?', [$code]);
}

function new_photo_code(): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $code;
}

/**
 * Save the photo the booth took. Re-encoded as JPEG, longest edge capped.
 * Returns [photo row, null] or [null, error].
 */
function booth_save_original(array $contest, array $file, string $name): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [null, upload_error_message((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE))];
    }
    if ($file['size'] > BOOTH_MAX_UPLOAD) {
        return [null, 'That photo is too large.'];
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        return [null, 'The photo could not be read.'];
    }
    $img = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
    if (!$img) {
        return [null, 'The photo could not be read.'];
    }
    $img = image_fit($img, BOOTH_MAX_EDGE);

    $dir = 'booth/' . (int) $contest['id'];
    if (!is_dir(media_dir() . '/' . $dir) && !mkdir(media_dir() . '/' . $dir, 0775, true)) {
        return [null, 'The server could not save the photo.'];
    }
    $code = new_photo_code();
    $relative = "{$dir}/{$code}-original.jpg";
    if (!imagejpeg($img, media_dir() . '/' . $relative, 90)) {
        return [null, 'The server could not save the photo.'];
    }

    db_run(
        'INSERT INTO booth_photos (code, contest_id, name, original_path, status, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$code, $contest['id'], $name, $relative, 'pending', utc_now()]
    );
    return [booth_photo_find((int) db()->lastInsertId()), null];
}

function image_fit(GdImage $img, int $maxEdge): GdImage
{
    [$w, $h] = [imagesx($img), imagesy($img)];
    $scale = min(1, $maxEdge / max($w, $h));
    if ($scale >= 1) {
        return $img;
    }
    $nw = (int) round($w * $scale);
    $nh = (int) round($h * $scale);
    $out = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $out;
}

/**
 * Restyle a booth photo with the chosen look. Runs the AI call (or the demo
 * filter when no API key is set) and stores the result. Returns the updated row.
 */
function booth_process(array $photo, array $contest, array $style): array
{
    @set_time_limit(240);
    ignore_user_abort(true);

    db_run(
        "UPDATE booth_photos SET status = 'processing', style = ?, error = '', ai_runs = ai_runs + 1 WHERE id = ?",
        [$style['name'], $photo['id']]
    );

    $source = media_dir() . '/' . $photo['original_path'];
    try {
        $jpeg = booth_ai_configured()
            ? openai_restyle($source, $style['prompt'] . ' ' . BOOTH_PROMPT_RULES)
            : demo_restyle($source, $style);
        $relative = dirname($photo['original_path']) . '/' . $photo['code'] . '-' . bin2hex(random_bytes(3)) . '.jpg';
        if (file_put_contents(media_dir() . '/' . $relative, $jpeg) === false) {
            throw new RuntimeException('The server could not save the new photo.');
        }
        delete_media($photo['result_path']);
        db_run(
            "UPDATE booth_photos SET status = 'done', result_path = ?, finished_at = ? WHERE id = ?",
            [$relative, utc_now(), $photo['id']]
        );
    } catch (Throwable $e) {
        error_log('Photobooth AI error: ' . $e->getMessage());
        db_run(
            "UPDATE booth_photos SET status = 'failed', error = ?, finished_at = ? WHERE id = ?",
            [mb_substr($e->getMessage(), 0, 255), utc_now(), $photo['id']]
        );
    }
    return booth_photo_find((int) $photo['id']);
}

/** Send the photo to OpenAI's image edit API. Returns JPEG bytes. */
function openai_restyle(string $sourcePath, string $prompt): string
{
    $fields = [
        'model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
        'prompt' => $prompt,
        'size' => '1024x1536',
        'quality' => env('OPENAI_IMAGE_QUALITY', 'medium'),
        'n' => '1',
    ];
    $fidelity = env('OPENAI_INPUT_FIDELITY', 'high');
    if ($fidelity !== 'off') {
        $fields['input_fidelity'] = $fidelity;
    }

    [$status, $body] = openai_post_image($sourcePath, $fields);
    // Some models don't accept input_fidelity; retry once without it.
    if ($status === 400 && isset($fields['input_fidelity']) && str_contains($body, 'input_fidelity')) {
        unset($fields['input_fidelity']);
        [$status, $body] = openai_post_image($sourcePath, $fields);
    }

    $json = json_decode($body, true);
    if ($status !== 200) {
        $message = $json['error']['message'] ?? "HTTP {$status}";
        if (($json['error']['code'] ?? '') === 'moderation_blocked' || str_contains((string) $message, 'safety')) {
            throw new RuntimeException('The AI service declined this photo. Try another look or retake the photo.');
        }
        throw new RuntimeException('The AI service returned an error: ' . $message);
    }
    $b64 = $json['data'][0]['b64_json'] ?? null;
    $bytes = $b64 ? base64_decode($b64, true) : false;
    $img = $bytes ? @imagecreatefromstring($bytes) : false;
    if (!$img) {
        throw new RuntimeException('The AI service sent back something that is not an image.');
    }
    ob_start();
    imagejpeg($img, null, 88);
    return (string) ob_get_clean();
}

/** @return array{0:int,1:string} */
function openai_post_image(string $sourcePath, array $fields): array
{
    $ch = curl_init('https://api.openai.com/v1/images/edits');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $fields + ['image' => new CURLFile($sourcePath, 'image/jpeg', 'photo.jpg')],
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . env('OPENAI_API_KEY')],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 200,
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        throw new RuntimeException('Could not reach the AI service: ' . $err);
    }
    return [(int) curl_getinfo($ch, CURLINFO_HTTP_CODE), (string) $body];
}

/**
 * Demo mode (no API key): tint the photo with the look's colors and label it,
 * so the whole booth can be tried without spending anything.
 */
function demo_restyle(string $sourcePath, array $style): string
{
    usleep(1_500_000);
    $img = imagecreatefromjpeg($sourcePath);
    if (!$img) {
        throw new RuntimeException('The photo could not be read.');
    }
    [$w, $h] = [imagesx($img), imagesy($img)];
    imagefilter($img, IMG_FILTER_GRAYSCALE);
    imagefilter($img, IMG_FILTER_CONTRAST, -20);

    $from = sscanf($style['from'], '#%02x%02x%02x');
    $to = sscanf($style['to'], '#%02x%02x%02x');
    $overlay = imagecreatetruecolor($w, $h);
    for ($y = 0; $y < $h; $y++) {
        $t = $y / max(1, $h - 1);
        $c = imagecolorallocate($overlay,
            (int) ($from[0] + ($to[0] - $from[0]) * $t),
            (int) ($from[1] + ($to[1] - $from[1]) * $t),
            (int) ($from[2] + ($to[2] - $from[2]) * $t));
        imageline($overlay, 0, $y, $w, $y, $c);
    }
    imagecopymerge($img, $overlay, 0, 0, 0, 0, $w, $h, 55);

    $band = (int) max(36, $h * 0.06);
    imagefilledrectangle($img, 0, $h - $band, $w, $h, imagecolorallocatealpha($img, 0, 0, 0, 50));
    $label = 'DEMO: ' . strtoupper($style['name']) . ' (no AI key set)';
    $font = 5;
    $tx = (int) (($w - imagefontwidth($font) * strlen($label)) / 2);
    imagestring($img, $font, max(8, $tx), $h - (int) ($band / 2) - 8, $label, imagecolorallocate($img, 255, 255, 255));

    ob_start();
    imagejpeg($img, null, 88);
    return (string) ob_get_clean();
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

function booth_photo_json(array $photo): array
{
    return [
        'id' => (int) $photo['id'],
        'code' => $photo['code'],
        'status' => $photo['status'],
        'style' => $photo['style'],
        'error' => $photo['error'],
        'original' => media_url($photo['original_path']),
        'result' => $photo['result_path'] ? media_url($photo['result_path']) : null,
        'inGallery' => (bool) $photo['in_gallery'],
    ];
}
