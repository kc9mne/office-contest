<?php
declare(strict_types=1);

/** /booth/{token}/... : the kiosk page and its JSON endpoints. */
function booth_route(string $sub): void
{
    $sub = '/' . trim($sub, '/');
    $contest = active_contest();

    if ($sub === '/') {
        booth_kiosk_page($contest);
        return;
    }

    // Everything below is called by the kiosk's JavaScript.
    if ($sub === '/session') {
        // The kiosk stays open all day; this refreshes its form token before each photo.
        json_response(['csrf' => csrf_token()]);
    }
    if (!$contest || !$contest['booth_enabled']) {
        json_response(['error' => 'The photobooth is turned off for this contest.'], 409);
    }

    if ($sub === '/photos' && is_post()) {
        booth_upload($contest);
    } elseif (preg_match('#^/photos/(\d+)(/(process|gallery))?$#', $sub, $m)) {
        $photo = booth_photo_find((int) $m[1]);
        if (!$photo || (int) $photo['contest_id'] !== (int) $contest['id']) {
            json_response(['error' => 'That photo was not found.'], 404);
        }
        $action = $m[3] ?? '';
        if ($action === '' && !is_post()) {
            json_response(booth_photo_json($photo));
        } elseif ($action === 'process' && is_post()) {
            booth_run($contest, $photo);
        } elseif ($action === 'gallery' && is_post()) {
            db_run('UPDATE booth_photos SET in_gallery = 1, name = ? WHERE id = ?', [post_str('name', 80), $photo['id']]);
            json_response(booth_photo_json(booth_photo_find((int) $photo['id'])) + ['shareUrl' => photo_share_url($photo['code'])]);
        }
    }
    json_response(['error' => 'Not found.'], 404);
}

function booth_kiosk_page(?array $contest): void
{
    $styles = $contest ? contest_booth_styles($contest) : [];
    $config = [
        'base' => url(booth_base()),
        'csrf' => csrf_token(),
        'verb' => $contest ? mode($contest['mode'])['booth_verb'] : '',
        'styles' => array_map(fn($s) => ['name' => $s['name'], 'from' => $s['from'], 'to' => $s['to']], $styles),
        'demo' => !booth_ai_configured(),
    ];
    $state = !$contest ? 'no_contest' : (!$contest['booth_enabled'] ? 'off' : 'ready');
    require APP_ROOT . '/src/views/booth.php';
}

function booth_upload(array $contest): void
{
    if (booth_limit_reached($contest)) {
        json_response(['error' => "The photobooth has reached today's photo limit. It resets at midnight."], 429);
    }
    [$photo, $error] = booth_save_original($contest, $_FILES['photo'] ?? [], post_str('name', 80));
    if ($error) {
        json_response(['error' => $error], 422);
    }
    json_response(booth_photo_json($photo), 201);
}

function booth_run(array $contest, array $photo): void
{
    $style = booth_style_by_name($contest, (string) ($_POST['style'] ?? ''));
    if (!$style) {
        json_response(['error' => 'Pick a look first.'], 422);
    }
    if ($photo['status'] === 'processing') {
        json_response(booth_photo_json($photo), 202);
    }
    if (booth_limit_reached($contest)) {
        json_response(['error' => "The photobooth has reached today's photo limit. It resets at midnight."], 429);
    }
    session_write_close(); // don't block other requests from this kiosk while the AI works
    $photo = booth_process($photo, $contest, $style);
    json_response(booth_photo_json($photo), $photo['status'] === 'done' ? 200 : 502);
}

function photo_share_url(string $code): string
{
    return site_origin() . url('/p/' . $code);
}

/** /p/{code}: one booth photo, for saving to your phone. */
function page_photo(string $code): void
{
    $photo = booth_photo_by_code($code);
    if (!$photo || $photo['status'] !== 'done') {
        abort(404, 'That photo was not found');
    }
    $contest = contest_find((int) $photo['contest_id']);
    view('photo', [
        'title' => 'Your photobooth picture',
        'photo' => $photo,
        'contest' => $contest,
        'mode' => $contest['mode'] ?? 'general',
    ]);
}

/** /qr?u=... : SVG QR code for a link on this site. */
function page_qr(): void
{
    $target = (string) ($_GET['u'] ?? '');
    if ($target === '' || strlen($target) > 200 || !str_starts_with($target, site_origin() . '/')) {
        abort(400, 'Bad QR request');
    }
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=86400');
    echo qr_svg($target);
}
