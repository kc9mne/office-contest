<?php
declare(strict_types=1);

const VIDEO_MAX_MB = 300;
const VIDEOS_PER_DEVICE = 5;
const VIDEO_TYPES = [
    'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm',
    'video/x-matroska' => 'mkv', 'video/3gpp' => '3gp', 'video/x-m4v' => 'm4v', 'video/mpeg' => 'mpg',
];

/** YouTube video ID from any common link format, or null. */
function youtube_id(string $url): ?string
{
    $url = trim($url);
    if (!preg_match('#^(https?://)?([a-z0-9-]+\.)?(youtube\.com|youtu\.be|youtube-nocookie\.com)/#i', $url)) {
        return null;
    }
    $patterns = [
        '#youtu\.be/([A-Za-z0-9_-]{11})#',
        '#[?&]v=([A-Za-z0-9_-]{11})#',
        '#/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})#',
    ];
    foreach ($patterns as $p) {
        if (preg_match($p, $url, $m)) {
            return $m[1];
        }
    }
    return null;
}

function video_find(int $id): ?array
{
    return db_one('SELECT * FROM videos WHERE id = ?', [$id]);
}

/** Videos everyone can see, newest first. */
function contest_videos(int $contestId): array
{
    return db_all("SELECT * FROM videos WHERE contest_id = ? AND status = 'ready' AND visible = 1 ORDER BY created_at DESC, id DESC", [$contestId]);
}

function contest_videos_admin(int $contestId): array
{
    return db_all('SELECT * FROM videos WHERE contest_id = ? ORDER BY created_at DESC, id DESC', [$contestId]);
}

function can_post_videos(array $contest): bool
{
    return is_admin() || $contest['video_posting'] !== 'admins';
}

function ffmpeg_path(): ?string
{
    static $path = false;
    if ($path === false) {
        $path = null;
        if (function_exists('exec')) {
            $found = trim((string) @shell_exec('command -v ffmpeg 2>/dev/null'));
            $path = $found !== '' ? $found : null;
        }
    }
    return $path;
}

/**
 * Save an uploaded video and queue it for conversion.
 * Returns [video row, null] or [null, error].
 */
function save_video_upload(array $contest, array $file, string $title, string $postedBy): array
{
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return [null, 'That video is over ' . VIDEO_MAX_MB . ' MB. Trim it on your phone, or post it to YouTube and paste the link instead.'];
    }
    if ($err !== UPLOAD_ERR_OK) {
        return [null, $err === UPLOAD_ERR_NO_FILE ? 'Choose a video first.' : upload_error_message((int) $err)];
    }
    if ($file['size'] > VIDEO_MAX_MB * 1024 * 1024) {
        return [null, 'That video is ' . round($file['size'] / 1048576) . ' MB. The limit is ' . VIDEO_MAX_MB . ' MB. Trim it, or post it to YouTube and paste the link instead.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
    $ext = VIDEO_TYPES[$mime] ?? null;
    if ($ext === null) {
        return [null, 'That file is not a video we can play. Use MP4 or MOV (what phones record).'];
    }
    if (!ffmpeg_path() && !in_array($ext, ['mp4', 'webm', 'm4v'], true)) {
        return [null, 'This server can only take MP4 or WebM videos. Post it to YouTube and paste the link instead.'];
    }
    $dir = 'videos/' . (int) $contest['id'];
    if (!is_dir(media_dir() . '/' . $dir) && !mkdir(media_dir() . '/' . $dir, 0775, true)) {
        return [null, 'The server could not save the video.'];
    }
    $base = $dir . '/' . bin2hex(random_bytes(8));
    $original = "{$base}-original.{$ext}";
    if (!move_uploaded_file($file['tmp_name'], media_dir() . '/' . $original)) {
        return [null, 'The server could not save the video.'];
    }
    $converting = ffmpeg_path() !== null;
    db_run(
        'INSERT INTO videos (contest_id, kind, title, posted_by, original_path, video_path, size_bytes, status, visible, device_id, ip, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$contest['id'], 'upload', $title, $postedBy, $original, $converting ? null : $original, (int) $file['size'],
         $converting ? 'processing' : 'ready', video_visible_on_post($contest), device_id(), client_ip(), utc_now()]
    );
    $id = (int) db()->lastInsertId();
    if ($converting) {
        start_video_job($id);
    }
    return [video_find($id), null];
}

function save_youtube_video(array $contest, string $url, string $title, string $postedBy): array
{
    $yt = youtube_id($url);
    if ($yt === null) {
        return [null, "That doesn't look like a YouTube link. Copy it from the Share button on YouTube."];
    }
    db_run(
        'INSERT INTO videos (contest_id, kind, title, posted_by, youtube_id, status, visible, device_id, ip, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$contest['id'], 'youtube', $title, $postedBy, $yt, 'ready', video_visible_on_post($contest), device_id(), client_ip(), utc_now()]
    );
    return [video_find((int) db()->lastInsertId()), null];
}

/** Admin posts show right away; others wait when the contest requires approval. */
function video_visible_on_post(array $contest): int
{
    return (is_admin() || !$contest['require_approval']) ? 1 : 0;
}

function device_video_count(int $contestId): int
{
    $row = db_one('SELECT COUNT(*) AS n FROM videos WHERE contest_id = ? AND device_id = ?', [$contestId, device_id()]);
    return (int) $row['n'];
}

/** Run bin/process-video.php in the background so the upload request can finish. */
function start_video_job(int $id): void
{
    $php = PHP_BINDIR . '/php';
    $script = APP_ROOT . '/bin/process-video.php';
    exec(escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . $id . ' > /dev/null 2>&1 &');
}

/**
 * Convert to H.264/AAC MP4 (max 1920px on the long side, starts playing before fully downloaded)
 * and grab a poster frame. Called from the background job.
 */
function process_video(int $id): void
{
    $video = video_find($id);
    if (!$video || $video['status'] !== 'processing') {
        return;
    }
    $ffmpeg = ffmpeg_path();
    $in = media_dir() . '/' . $video['original_path'];
    $base = preg_replace('/-original\.[a-z0-9]+$/', '', $video['original_path']);
    $outRel = "{$base}.mp4";
    $posterRel = "{$base}-poster.jpg";
    $out = media_dir() . '/' . $outRel;
    $poster = media_dir() . '/' . $posterRel;

    try {
        if (!$ffmpeg || !is_file($in)) {
            throw new RuntimeException('The video converter is not available.');
        }
        $scale = "scale='if(gt(iw,ih),min(1920,iw),-2)':'if(gt(iw,ih),-2,min(1920,ih))'";
        $cmd = escapeshellarg($ffmpeg) . ' -y -hide_banner -loglevel error -i ' . escapeshellarg($in)
            . ' -vf ' . escapeshellarg($scale) . ' -c:v libx264 -preset veryfast -crf 23 -pix_fmt yuv420p'
            . ' -c:a aac -b:a 128k -ac 2 -movflags +faststart ' . escapeshellarg($out . '.part.mp4') . ' 2>&1';
        exec($cmd, $output, $code);
        if ($code !== 0 || !is_file($out . '.part.mp4')) {
            throw new RuntimeException('Conversion failed: ' . mb_substr(implode(' ', $output), 0, 200));
        }
        rename($out . '.part.mp4', $out);

        $duration = (float) trim((string) shell_exec(escapeshellarg(dirname($ffmpeg) . '/ffprobe') . ' -v error -show_entries format=duration -of csv=p=0 ' . escapeshellarg($out)));
        $at = $duration > 2 ? 1 : 0;
        exec(escapeshellarg($ffmpeg) . " -y -hide_banner -loglevel error -ss {$at} -i " . escapeshellarg($out)
            . ' -frames:v 1 -vf ' . escapeshellarg("scale='min(960,iw)':-2") . ' -q:v 4 ' . escapeshellarg($poster) . ' 2>&1');

        db_run(
            "UPDATE videos SET status = 'ready', video_path = ?, poster_path = ?, duration_sec = ?, error = '' WHERE id = ?",
            [$outRel, is_file($poster) ? $posterRel : null, $duration > 0 ? (int) round($duration) : null, $id]
        );
        if ($video['original_path'] !== $outRel) {
            delete_media($video['original_path']);
        }
    } catch (Throwable $e) {
        @unlink($out . '.part.mp4');
        db_run("UPDATE videos SET status = 'failed', error = ? WHERE id = ?", [mb_substr($e->getMessage(), 0, 255), $id]);
    }
}

function video_delete(array $video): void
{
    foreach (['original_path', 'video_path', 'poster_path'] as $col) {
        delete_media($video[$col]);
    }
    db_run('DELETE FROM videos WHERE id = ?', [$video['id']]);
}

function format_duration(?int $seconds): string
{
    if (!$seconds) {
        return '';
    }
    return intdiv($seconds, 60) . ':' . sprintf('%02d', $seconds % 60);
}
