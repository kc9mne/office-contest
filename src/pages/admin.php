<?php
declare(strict_types=1);

function admin_route(string $sub): void
{
    $sub = '/' . trim($sub, '/');

    if (!is_admin()) {
        admin_login_page($sub);
        return;
    }

    if ($sub === '/') {
        admin_dashboard();
    } elseif ($sub === '/logout' && is_post()) {
        admin_sign_out();
        redirect('/');
    } elseif ($sub === '/settings') {
        admin_settings();
    } elseif ($sub === '/gallery') {
        admin_gallery();
    } elseif ($sub === '/contests/new') {
        admin_contest_form(null);
    } elseif (preg_match('#^/contests/(\d+)$#', $sub, $m)) {
        admin_contest_form((int) $m[1]);
    } elseif (preg_match('#^/contests/(\d+)/status$#', $sub, $m) && is_post()) {
        admin_contest_status((int) $m[1]);
    } else {
        abort(404);
    }
}

function admin_url(string $sub = ''): string
{
    return url(admin_base() . ($sub === '' ? '' : '/' . ltrim($sub, '/')));
}

function admin_full_link(): string
{
    return site_origin() . admin_url();
}

function admin_login_page(string $sub): void
{
    $error = null;
    if (is_post() && isset($_POST['pin'])) {
        $error = admin_login((string) $_POST['pin']);
        if ($error === null) {
            redirect(admin_base() . ($sub === '/' ? '' : $sub));
        }
    } elseif (login_locked()) {
        $error = 'Too many wrong PINs. Wait ' . LOGIN_WINDOW_MINUTES . ' minutes and try again.';
    }
    view('admin/login', ['title' => 'Admin sign-in', 'error' => $error], 'layout_plain');
}

function admin_dashboard(): void
{
    $justInstalled = !empty($_SESSION['just_installed']);
    unset($_SESSION['just_installed']);
    view('admin/dashboard', [
        'title' => 'Admin',
        'contests' => contest_list(),
        'justInstalled' => $justInstalled,
        'adminLink' => admin_full_link(),
    ], 'layout_admin');
}

function admin_contest_status(int $id): void
{
    $contest = contest_find($id) ?? abort(404);
    $status = (string) ($_POST['status'] ?? '');
    if (!in_array($status, ['active', 'draft', 'archived'], true)) {
        abort(400, 'Unknown status.');
    }
    contest_set_status($id, $status);
    flash(match ($status) {
        'active' => "“{$contest['title']}” is now live on the home page.",
        'archived' => "“{$contest['title']}” moved to the archive.",
        default => "“{$contest['title']}” is no longer on the home page.",
    });
    redirect(admin_base());
}

function admin_contest_form(?int $id): void
{
    $contest = $id ? (contest_find($id) ?? abort(404)) : null;
    $errors = [];

    if (is_post()) {
        [$data, $categories, $errors] = contest_from_post();
        if (!$errors) {
            $newId = contest_save($id, $data, $categories);
            flash($id ? 'Contest saved.' : 'Contest created. Make it live when you are ready.');
            redirect(admin_base() . '/contests/' . $newId);
        }
        $form = $data + ['categories' => implode("\n", $categories)];
        $form['starts_at'] = $data['starts_local'];
        $form['ends_at'] = $data['ends_local'];
        $form['styles'] = $data['booth_style_list'];
    } elseif ($contest) {
        $form = $contest;
        $form['starts_at'] = utc_to_local($contest['starts_at'], 'Y-m-d\TH:i');
        $form['ends_at'] = utc_to_local($contest['ends_at'], 'Y-m-d\TH:i');
        $form['categories'] = implode("\n", array_column(contest_categories($id), 'name'));
        $form['styles'] = contest_booth_styles($contest);
    } else {
        $m = mode('halloween');
        $start = (new DateTimeImmutable('tomorrow 09:00', site_tz()));
        $form = [
            'mode' => 'halloween',
            'title' => $m['title'] . ' ' . $start->format('Y'),
            'subtitle' => '',
            'starts_at' => $start->format('Y-m-d\TH:i'),
            'ends_at' => $start->modify('+3 days 15:00')->format('Y-m-d\TH:i'),
            'categories' => implode("\n", $m['categories']),
            'event_name' => $m['event_name'],
            'event_details' => '',
            'require_approval' => 0,
            'show_counts' => 1,
            'booth_enabled' => $m['booth'] ? 1 : 0,
            'booth_daily_limit' => 200,
            'styles' => $m['booth_styles'],
        ];
    }

    view('admin/contest_form', [
        'title' => $contest ? 'Edit contest' : 'New contest',
        'contest' => $contest,
        'form' => $form,
        'errors' => $errors,
    ], 'layout_admin');
}

function admin_gallery(): void
{
    $contest = active_contest();
    if (is_post() && $contest) {
        $photo = booth_photo_find((int) ($_POST['photo_id'] ?? 0));
        if (!$photo || (int) $photo['contest_id'] !== (int) $contest['id']) {
            abort(404);
        }
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'hide' || $action === 'show') {
            db_run('UPDATE booth_photos SET in_gallery = ? WHERE id = ?', [$action === 'show' ? 1 : 0, $photo['id']]);
            flash($action === 'show' ? 'Photo is back in the gallery.' : 'Photo removed from the gallery. Its share link still works for the person who took it.');
        } elseif ($action === 'delete') {
            booth_photo_delete($photo);
            flash('Photo deleted for good, including its share link.');
        }
        redirect(admin_base() . '/gallery');
    }
    view('admin/gallery', [
        'title' => 'Gallery',
        'contest' => $contest,
        'photos' => $contest ? booth_admin_photos((int) $contest['id']) : [],
    ], 'layout_admin');
}

function admin_settings(): void
{
    $errors = [];
    $notice = null;

    if (is_post()) {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'branding') {
            $name = post_str('company_name', 80);
            $tz = post_str('timezone', 64);
            $color = strtoupper(post_str('brand_color', 7));
            if ($name === '') {
                $errors['company_name'] = 'Enter your company name.';
            }
            if (!in_array($tz, DateTimeZone::listIdentifiers(), true)) {
                $errors['timezone'] = 'Choose your time zone.';
            }
            if (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
                $errors['brand_color'] = 'Pick a color.';
            }
            $upload = $_FILES['logo'] ?? null;
            $newLogo = null;
            if ($upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                [$newLogo, $err] = save_logo_upload($upload);
                if ($err) {
                    $errors['logo'] = $err;
                }
            }
            if (!$errors) {
                set_setting('company_name', $name);
                set_setting('timezone', $tz);
                set_setting('brand_color', $color);
                set_setting('use_brand_color', post_bool('use_brand_color') ? '1' : '0');
                if ($newLogo) {
                    delete_media(setting('logo_path'));
                    set_setting('logo_path', $newLogo);
                } elseif (post_bool('remove_logo')) {
                    delete_media(setting('logo_path'));
                    set_setting('logo_path', '');
                }
                flash('Branding saved.');
                redirect(admin_base() . '/settings');
            } elseif ($newLogo) {
                delete_media($newLogo);
            }
        } elseif ($action === 'pin') {
            $current = (string) ($_POST['current_pin'] ?? '');
            $pin = (string) ($_POST['new_pin'] ?? '');
            if (!password_verify($current, (string) setting('admin_pin_hash'))) {
                $errors['current_pin'] = 'Your current PIN is not right.';
            } elseif (!valid_pin($pin)) {
                $errors['new_pin'] = 'Use 4 to 12 digits for the PIN.';
            } elseif ($pin !== (string) ($_POST['new_pin_confirm'] ?? '')) {
                $errors['new_pin_confirm'] = 'The two PINs do not match.';
            }
            if (!$errors) {
                set_setting('admin_pin_hash', password_hash($pin, PASSWORD_DEFAULT));
                flash('PIN changed.');
                redirect(admin_base() . '/settings');
            }
        } elseif ($action === 'ai_key_save') {
            $key = preg_replace('/\s+/', '', (string) ($_POST['openai_key'] ?? ''));
            if (strlen($key) < 20 || strlen($key) > 400) {
                $errors['openai_key'] = 'Paste the whole key. OpenAI keys start with "sk-" and are long.';
            } else {
                [$ok, $message] = openai_check_key($key);
                $unreachable = str_starts_with($message, 'Could not reach');
                if ($ok || $unreachable) {
                    set_setting('openai_api_key_enc', encrypt_secret($key));
                    flash($ok ? "Key saved. {$message}" : "Key saved, but it couldn't be checked. {$message}");
                    redirect(admin_base() . '/settings#ai');
                }
                $errors['openai_key'] = $message;
            }
        } elseif ($action === 'ai_key_test') {
            $key = openai_api_key();
            if ($key === null) {
                $errors['openai_key'] = 'There is no key to test yet.';
            } else {
                [$ok, $message] = openai_check_key($key);
                if ($ok) {
                    flash($message);
                    redirect(admin_base() . '/settings#ai');
                }
                $errors['openai_key'] = $message;
            }
        } elseif ($action === 'ai_key_remove') {
            set_setting('openai_api_key_enc', '');
            flash('Key removed. The photobooth is in demo mode until you add one.');
            redirect(admin_base() . '/settings#ai');
        } elseif ($action === 'ai_options') {
            $model = post_str('openai_model', 60);
            $quality = post_str('openai_quality', 10);
            if (!preg_match('/^[a-z0-9][a-z0-9.\-]*$/i', $model)) {
                $errors['openai_model'] = 'Enter a model name, like gpt-image-1.';
            }
            if (!in_array($quality, ['low', 'medium', 'high'], true)) {
                $errors['openai_quality'] = 'Pick a quality.';
            }
            if (!$errors) {
                set_setting('openai_model', $model);
                set_setting('openai_quality', $quality);
                flash('AI options saved.');
                redirect(admin_base() . '/settings#ai');
            }
        } elseif ($action === 'new_booth_link') {
            set_setting('booth_token', bin2hex(random_bytes(8)));
            flash('New photobooth link created. Open it on the booth device; the old link no longer works.');
            redirect(admin_base() . '/settings');
        } elseif ($action === 'new_link') {
            if (!post_bool('confirm_new_link')) {
                $errors['confirm_new_link'] = 'Tick the box to confirm. The old admin link stops working.';
            } else {
                set_setting('admin_token', new_admin_token());
                admin_sign_in();
                flash('New admin link created. Bookmark it now; the old link no longer works.');
                redirect(admin_base() . '/settings');
            }
        }
    }

    view('admin/settings', [
        'title' => 'Site settings',
        'errors' => $errors,
        'adminLink' => admin_full_link(),
        'boothLink' => site_origin() . url(booth_base()),
        'boothRunsToday' => booth_runs_today(),
        'activeContest' => active_contest(),
    ], 'layout_admin');
}
