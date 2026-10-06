<?php
declare(strict_types=1);

function contest_find(int $id): ?array
{
    return db_one('SELECT * FROM contests WHERE id = ?', [$id]);
}

function active_contest(): ?array
{
    return db_one("SELECT * FROM contests WHERE status = 'active' ORDER BY id DESC LIMIT 1");
}

function contest_list(): array
{
    return db_all("SELECT * FROM contests ORDER BY FIELD(status, 'active', 'draft', 'archived'), starts_at DESC");
}

function contest_categories(int $contestId): array
{
    return db_all('SELECT * FROM categories WHERE contest_id = ? ORDER BY sort_order, id', [$contestId]);
}

/** before | live | closed, from the start and end times. */
function contest_phase(array $contest, ?int $now = null): string
{
    $now ??= time();
    if ($now < utc_timestamp($contest['starts_at'])) {
        return 'before';
    }
    return $now < utc_timestamp($contest['ends_at']) ? 'live' : 'closed';
}

function phase_label(string $phase): string
{
    return ['before' => 'Not started', 'live' => 'Voting open', 'closed' => 'Voting closed'][$phase] ?? $phase;
}

/**
 * Validate the contest form. Returns [data, categories, errors].
 */
function contest_from_post(): array
{
    $errors = [];
    $mode = (string) ($_POST['mode'] ?? '');
    if (!isset(modes()[$mode])) {
        $errors['mode'] = 'Choose a contest type.';
        $mode = 'general';
    }

    $data = [
        'mode' => $mode,
        'title' => post_str('title', 120),
        'subtitle' => post_str('subtitle', 120),
        'event_name' => post_str('event_name', 80),
        'event_details' => post_str('event_details', 160),
        'require_approval' => post_bool('require_approval') ? 1 : 0,
        'show_counts' => post_bool('show_counts') ? 1 : 0,
        'booth_enabled' => post_bool('booth_enabled') ? 1 : 0,
        'starts_local' => post_str('starts_at', 20),
        'ends_local' => post_str('ends_at', 20),
    ];

    if ($data['title'] === '') {
        $errors['title'] = 'Give the contest a title.';
    }
    $data['starts_at'] = local_input_to_utc($data['starts_local']);
    $data['ends_at'] = local_input_to_utc($data['ends_local']);
    if ($data['starts_at'] === null) {
        $errors['starts_at'] = 'Pick when voting opens.';
    }
    if ($data['ends_at'] === null) {
        $errors['ends_at'] = 'Pick when voting closes.';
    } elseif ($data['starts_at'] !== null && $data['ends_at'] <= $data['starts_at']) {
        $errors['ends_at'] = 'Voting has to close after it opens.';
    }

    $categories = [];
    foreach (preg_split('/\R/', (string) ($_POST['categories'] ?? '')) as $line) {
        $name = mb_substr(trim($line), 0, 60);
        if ($name !== '' && !in_array(mb_strtolower($name), array_map('mb_strtolower', $categories), true)) {
            $categories[] = $name;
        }
    }
    if (!$categories) {
        $errors['categories'] = 'Add at least one category.';
    } elseif (count($categories) > 10) {
        $errors['categories'] = 'Use 10 categories or fewer.';
    }

    return [$data, $categories, $errors];
}

function contest_save(?int $id, array $data, array $categories): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $fields = ['mode', 'title', 'subtitle', 'starts_at', 'ends_at', 'event_name', 'event_details',
            'require_approval', 'show_counts', 'booth_enabled'];
        $values = array_map(fn($f) => $data[$f], $fields);
        if ($id === null) {
            db_run(
                'INSERT INTO contests (' . implode(', ', $fields) . ', status, created_at, updated_at)
                 VALUES (' . str_repeat('?, ', count($fields)) . "'draft', ?, ?)",
                [...$values, utc_now(), utc_now()]
            );
            $id = (int) $pdo->lastInsertId();
        } else {
            db_run(
                'UPDATE contests SET ' . implode(' = ?, ', $fields) . ' = ?, updated_at = ? WHERE id = ?',
                [...$values, utc_now(), $id]
            );
        }
        categories_sync($id, $categories);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $id;
}

/** Keep existing categories (by name) so their votes survive edits; add new ones, remove dropped ones. */
function categories_sync(int $contestId, array $names): void
{
    $existing = [];
    foreach (contest_categories($contestId) as $row) {
        $existing[mb_strtolower($row['name'])] = $row;
    }
    $keep = [];
    foreach (array_values($names) as $i => $name) {
        $key = mb_strtolower($name);
        if (isset($existing[$key])) {
            db_run('UPDATE categories SET name = ?, sort_order = ? WHERE id = ?', [$name, $i, $existing[$key]['id']]);
            $keep[$key] = true;
        } else {
            db_run('INSERT INTO categories (contest_id, name, sort_order) VALUES (?, ?, ?)', [$contestId, $name, $i]);
        }
    }
    foreach ($existing as $key => $row) {
        if (!isset($keep[$key])) {
            db_run('DELETE FROM categories WHERE id = ?', [$row['id']]);
        }
    }
}

function contest_set_status(int $id, string $status): void
{
    $pdo = db();
    $pdo->beginTransaction();
    if ($status === 'active') {
        db_run("UPDATE contests SET status = 'draft' WHERE status = 'active'");
    }
    db_run('UPDATE contests SET status = ?, updated_at = ? WHERE id = ?', [$status, utc_now(), $id]);
    $pdo->commit();
}
