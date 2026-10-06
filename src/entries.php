<?php
declare(strict_types=1);

const ENTRY_MAX_UPLOAD = 15 * 1024 * 1024;
const ENTRY_MAX_EDGE = 1600;
const ENTRIES_PER_DEVICE = 3;

/**
 * This browser's device ID: a random value in a long-lived cookie.
 * Used for "one vote per device"; clearing cookies gets a new one, which the voter list helps catch.
 */
function device_id(): string
{
    static $id = null;
    if ($id !== null) {
        return $id;
    }
    $cookie = (string) ($_COOKIE['ov_device'] ?? '');
    $id = preg_match('/^[a-f0-9]{32}$/', $cookie) ? $cookie : bin2hex(random_bytes(16));
    if ($cookie !== $id) {
        setcookie('ov_device', $id, [
            'expires' => time() + 60 * 60 * 24 * 730,
            'path' => base_path() ?: '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    return $id;
}

function site_departments(): array
{
    $list = preg_split('/\R/', (string) setting('departments', '')) ?: [];
    return array_values(array_filter(array_map('trim', $list), fn($d) => $d !== ''));
}

// ---------- entries ----------

function entry_find(int $id): ?array
{
    return db_one('SELECT * FROM entries WHERE id = ?', [$id]);
}

function contest_entries(int $contestId, ?string $status = 'approved'): array
{
    if ($status === null) {
        return db_all("SELECT * FROM entries WHERE contest_id = ? ORDER BY FIELD(status, 'pending', 'approved', 'rejected'), created_at DESC", [$contestId]);
    }
    return db_all('SELECT * FROM entries WHERE contest_id = ? AND status = ? ORDER BY created_at ASC, id ASC', [$contestId, $status]);
}

function device_entry_count(int $contestId): int
{
    $row = db_one('SELECT COUNT(*) AS n FROM entries WHERE contest_id = ? AND device_id = ?', [$contestId, device_id()]);
    return (int) $row['n'];
}

/** Load an uploaded photo and turn it upright using the camera's EXIF orientation. */
function image_from_upload(string $path, int $type): ?GdImage
{
    $img = @imagecreatefromstring((string) file_get_contents($path));
    if (!$img) {
        return null;
    }
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        switch ((int) ($exif['Orientation'] ?? 1)) {
            case 2: imageflip($img, IMG_FLIP_HORIZONTAL); break;
            case 3: $img = imagerotate($img, 180, 0); break;
            case 4: imageflip($img, IMG_FLIP_VERTICAL); break;
            case 5: $img = imagerotate($img, -90, 0); imageflip($img, IMG_FLIP_HORIZONTAL); break;
            case 6: $img = imagerotate($img, -90, 0); break;
            case 7: $img = imagerotate($img, 90, 0); imageflip($img, IMG_FLIP_HORIZONTAL); break;
            case 8: $img = imagerotate($img, 90, 0); break;
        }
    }
    return $img;
}

/** Returns [relative path, null] or [null, error]. */
function save_entry_photo(array $contest, array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [null, ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? 'Add a photo.' : upload_error_message((int) $file['error'])];
    }
    if ($file['size'] > ENTRY_MAX_UPLOAD) {
        return [null, 'That photo is over 15 MB. Try a smaller one.'];
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
        return [null, 'Use a JPG, PNG or WebP photo. (On an iPhone, take the photo from this page and it is converted for you.)'];
    }
    $img = image_from_upload($file['tmp_name'], $info[2]);
    if (!$img) {
        return [null, 'That photo could not be read. Try taking it again.'];
    }
    $img = image_fit($img, ENTRY_MAX_EDGE);
    $dir = 'entries/' . (int) $contest['id'];
    if (!is_dir(media_dir() . '/' . $dir) && !mkdir(media_dir() . '/' . $dir, 0775, true)) {
        return [null, 'The server could not save the photo.'];
    }
    $relative = $dir . '/' . bin2hex(random_bytes(8)) . '.jpg';
    if (!imagejpeg($img, media_dir() . '/' . $relative, 85)) {
        return [null, 'The server could not save the photo.'];
    }
    return [$relative, null];
}

function entry_delete(array $entry): void
{
    delete_media($entry['photo_path']);
    db_run('DELETE FROM entries WHERE id = ?', [$entry['id']]);
}

// ---------- voters and votes ----------

function current_voter(int $contestId): ?array
{
    return db_one('SELECT * FROM voters WHERE contest_id = ? AND device_id = ?', [$contestId, device_id()]);
}

function voter_save_name(int $contestId, string $name): void
{
    db_run(
        'INSERT INTO voters (contest_id, device_id, name, ip, user_agent, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), ip = VALUES(ip), user_agent = VALUES(user_agent), updated_at = VALUES(updated_at)',
        [$contestId, device_id(), $name, client_ip(), mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), utc_now(), utc_now()]
    );
}

/** category_id => entry_id for this voter. */
function voter_picks(?array $voter): array
{
    if (!$voter) {
        return [];
    }
    $picks = [];
    foreach (db_all('SELECT category_id, entry_id FROM votes WHERE voter_id = ?', [$voter['id']]) as $row) {
        $picks[(int) $row['category_id']] = (int) $row['entry_id'];
    }
    return $picks;
}

function cast_vote(array $voter, int $categoryId, int $entryId): void
{
    db_run(
        'INSERT INTO votes (contest_id, voter_id, category_id, entry_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE entry_id = VALUES(entry_id), updated_at = VALUES(updated_at)',
        [$voter['contest_id'], $voter['id'], $categoryId, $entryId, utc_now(), utc_now()]
    );
    db_run('UPDATE voters SET ip = ?, updated_at = ? WHERE id = ?', [client_ip(), utc_now(), $voter['id']]);
}

// ---------- standings ----------

/**
 * Rank entries by votes. Ties share a place ("1, 1, 3").
 * @param array<int,int> $scores entry_id => votes
 * @return list<array{entry: array, votes: int, place: int, tied: bool}>
 */
function rank_entries(array $entries, array $scores): array
{
    $rows = [];
    foreach ($entries as $e) {
        $rows[] = ['entry' => $e, 'votes' => (int) ($scores[(int) $e['id']] ?? 0)];
    }
    usort($rows, fn($a, $b) => [$b['votes'], $a['entry']['name']] <=> [$a['votes'], $b['entry']['name']]);
    $counts = array_count_values(array_column($rows, 'votes'));
    foreach ($rows as $i => $row) {
        $higher = 0;
        foreach ($rows as $other) {
            if ($other['votes'] > $row['votes']) {
                $higher++;
            }
        }
        $rows[$i]['place'] = $higher + 1;
        $rows[$i]['tied'] = $counts[$row['votes']] > 1;
    }
    return $rows;
}

/** Overall and per-category rankings for a contest, ignoring voided voters. */
function contest_standings(array $contest): array
{
    $id = (int) $contest['id'];
    $entries = contest_entries($id);
    $categories = contest_categories($id);
    $perCategory = [];
    $totals = [];
    $rows = db_all(
        'SELECT v.category_id, v.entry_id, COUNT(*) AS n
         FROM votes v JOIN voters r ON r.id = v.voter_id AND r.voided = 0
         WHERE v.contest_id = ? GROUP BY v.category_id, v.entry_id',
        [$id]
    );
    foreach ($rows as $r) {
        $perCategory[(int) $r['category_id']][(int) $r['entry_id']] = (int) $r['n'];
        $totals[(int) $r['entry_id']] = ($totals[(int) $r['entry_id']] ?? 0) + (int) $r['n'];
    }
    $byCategory = [];
    foreach ($categories as $c) {
        $byCategory[] = ['category' => $c, 'ranked' => rank_entries($entries, $perCategory[(int) $c['id']] ?? [])];
    }
    $voters = db_one('SELECT COUNT(DISTINCT v.voter_id) AS n FROM votes v JOIN voters r ON r.id = v.voter_id AND r.voided = 0 WHERE v.contest_id = ?', [$id]);
    return [
        'entries' => $entries,
        'overall' => rank_entries($entries, $totals),
        'categories' => $byCategory,
        'totalVotes' => array_sum($totals),
        'voters' => (int) $voters['n'],
    ];
}

/** Leaders of a ranked list (everyone in first place), or [] if nobody has votes yet. */
function ranked_leaders(array $ranked): array
{
    return array_values(array_filter($ranked, fn($r) => $r['place'] === 1 && $r['votes'] > 0));
}

function place_label(array $row): string
{
    $suffix = [1 => 'ST', 2 => 'ND', 3 => 'RD'][$row['place']] ?? 'TH';
    return ($row['tied'] ? 'T-' : '') . $row['place'] . $suffix;
}
