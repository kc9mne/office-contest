<?php
declare(strict_types=1);

function page_home(): void
{
    $contest = active_contest();
    $vars = [
        'title' => $contest['title'] ?? company_name(),
        'contest' => $contest,
        'mode' => $contest['mode'] ?? 'general',
    ];
    if ($contest) {
        $vars['phase'] = contest_phase($contest);
        $vars['standings'] = contest_standings($contest);
        $vars['galleryCount'] = count(booth_gallery_photos((int) $contest['id'])) + count($vars['standings']['entries']);
        $vars['showResults'] = $vars['phase'] === 'closed' || ($vars['phase'] === 'live' && $contest['show_counts']);
    }
    view('home', $vars);
}

function page_gallery(): void
{
    $contest = active_contest();
    view('gallery', [
        'title' => 'Gallery',
        'contest' => $contest,
        'mode' => $contest['mode'] ?? 'general',
        'photos' => $contest ? booth_gallery_photos((int) $contest['id']) : [],
        'entries' => $contest ? contest_entries((int) $contest['id']) : [],
    ]);
}

/** /session: a fresh form token for pages left open a long time (phones, kiosks). */
function page_session(): void
{
    json_response(['csrf' => csrf_token()]);
}

function page_join(): void
{
    $contest = active_contest();
    if (!$contest) {
        redirect('/');
    }
    device_id();
    $phase = contest_phase($contest);
    $errors = [];
    $form = ['name' => '', 'department' => '', 'title' => '', 'consent' => true];

    if (is_post() && $phase !== 'closed') {
        $form = [
            'name' => post_str('name', 100),
            'department' => post_str('department', 80),
            'title' => post_str('title', 100),
            'consent' => post_bool('consent'),
        ];
        $departments = site_departments();
        if ($form['name'] === '') {
            $errors['name'] = 'Enter your name.';
        }
        if ($form['department'] === '') {
            $errors['department'] = 'Choose your department.';
        } elseif ($departments && !in_array($form['department'], $departments, true)) {
            $errors['department'] = 'Choose your department from the list.';
        }
        if (!$form['consent']) {
            $errors['consent'] = 'Tick the box so your photo can be shown in the gallery and on screens.';
        }
        if (device_entry_count((int) $contest['id']) >= ENTRIES_PER_DEVICE) {
            $errors['photo'] = 'This phone has already sent ' . ENTRIES_PER_DEVICE . ' entries. Ask an organizer if you need another.';
        }
        $photo = null;
        if (!$errors) {
            [$photo, $err] = save_entry_photo($contest, $_FILES['photo'] ?? []);
            if ($err) {
                $errors['photo'] = $err;
            }
        }
        if (!$errors) {
            $status = $contest['require_approval'] ? 'pending' : 'approved';
            db_run(
                'INSERT INTO entries (contest_id, name, department, title, photo_path, status, device_id, ip, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$contest['id'], $form['name'], $form['department'], $form['title'], $photo, $status, device_id(), client_ip(), utc_now()]
            );
            $_SESSION['joined'] = (int) db()->lastInsertId();
            redirect('/join/done');
        }
    }

    view('join', [
        'title' => 'Enter the contest',
        'contest' => $contest,
        'mode' => $contest['mode'],
        'phase' => $phase,
        'noun' => mode($contest['mode'])['noun'],
        'form' => $form,
        'errors' => $errors,
        'departments' => site_departments(),
    ]);
}

function page_join_done(): void
{
    $contest = active_contest();
    $entry = isset($_SESSION['joined']) ? entry_find((int) $_SESSION['joined']) : null;
    if (!$contest || !$entry || (int) $entry['contest_id'] !== (int) $contest['id']) {
        redirect('/join');
    }
    view('join_done', [
        'title' => "You're entered",
        'contest' => $contest,
        'mode' => $contest['mode'],
        'entry' => $entry,
        'phase' => contest_phase($contest),
    ]);
}

function page_vote(): void
{
    $contest = active_contest();
    if (!$contest) {
        redirect('/');
    }
    device_id();
    $id = (int) $contest['id'];
    $phase = contest_phase($contest);
    $categories = contest_categories($id);
    $voter = current_voter($id);

    if (is_post()) {
        $wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
        $fail = function (string $message, int $code = 422) use ($wantsJson): never {
            if ($wantsJson) {
                json_response(['error' => $message], $code);
            }
            flash($message);
            redirect('/vote');
        };

        if (isset($_POST['voter_name'])) {
            $name = (string) preg_replace('/\s+/', ' ', post_str('voter_name', 80));
            if (mb_strlen($name) < 2) {
                $_SESSION['name_error'] = 'Enter your name so organizers can match your vote to you.';
                redirect('/vote');
            }
            voter_save_name($id, $name);
            redirect('/vote' . (isset($_GET['cat']) ? '?cat=' . (int) $_GET['cat'] : ''));
        }

        if ($phase !== 'live') {
            $fail($phase === 'before' ? 'Voting has not opened yet.' : 'Voting has closed.', 409);
        }
        if (!$voter) {
            $fail('Enter your name first, then vote.', 403);
        }
        if ($voter['voided']) {
            $fail('Votes from this device are not being counted. Ask an organizer.', 403);
        }
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $category = null;
        foreach ($categories as $c) {
            if ((int) $c['id'] === $categoryId) {
                $category = $c;
            }
        }
        $entry = entry_find((int) ($_POST['entry_id'] ?? 0));
        if (!$category || !$entry || (int) $entry['contest_id'] !== $id || $entry['status'] !== 'approved') {
            $fail('That vote could not be counted. Reload the page and try again.');
        }
        cast_vote($voter, $categoryId, (int) $entry['id']);
        $picks = voter_picks($voter);
        $message = "{$category['name']}: vote saved for {$entry['name']}.";
        if ($wantsJson) {
            json_response(['ok' => true, 'message' => $message, 'picks' => $picks, 'done' => count($picks), 'total' => count($categories)]);
        }
        flash($message);
        redirect('/vote?cat=' . $categoryId . '#entry-' . $entry['id']);
    }

    $catId = (int) ($_GET['cat'] ?? 0);
    $current = $categories[0] ?? null;
    foreach ($categories as $c) {
        if ((int) $c['id'] === $catId) {
            $current = $c;
        }
    }
    $nameError = $_SESSION['name_error'] ?? null;
    unset($_SESSION['name_error']);

    view('vote', [
        'title' => 'Vote',
        'contest' => $contest,
        'mode' => $contest['mode'],
        'phase' => $phase,
        'categories' => $categories,
        'current' => $current,
        'entries' => contest_entries($id),
        'voter' => $voter,
        'picks' => voter_picks($voter),
        'nameError' => $nameError,
        'changingName' => isset($_GET['name']),
    ]);
}
