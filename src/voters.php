<?php
declare(strict_types=1);

const BURST_WINDOW_SECONDS = 120;
const BURST_MIN_VOTERS = 4;

/** "Marcus  J. Tate" -> "marcus j tate", for comparing names people typed. */
function normalize_name(string $name): string
{
    $name = mb_strtolower($name);
    $name = (string) preg_replace('/[^\p{L}\p{N}\s]/u', '', $name);
    return trim((string) preg_replace('/\s+/', ' ', $name));
}

/** "First Last" ignoring middle names/initials, for a looser match. */
function name_key_first_last(string $normalized): string
{
    $parts = explode(' ', $normalized);
    return count($parts) >= 2 ? $parts[0] . ' ' . end($parts) : $normalized;
}

/** Short, readable device label from the random device ID. */
function device_label(string $deviceId): string
{
    return strtoupper(substr($deviceId, 0, 4));
}

/** "iPhone · Safari", "Windows · Edge" from a user agent string. */
function browser_label(string $ua): string
{
    $device = match (true) {
        str_contains($ua, 'iPhone') => 'iPhone',
        str_contains($ua, 'iPad') => 'iPad',
        str_contains($ua, 'Android') => 'Android',
        str_contains($ua, 'Windows') => 'Windows',
        str_contains($ua, 'Macintosh') => 'Mac',
        str_contains($ua, 'CrOS') => 'Chromebook',
        str_contains($ua, 'Linux') => 'Linux',
        default => 'Unknown device',
    };
    $browser = match (true) {
        str_contains($ua, 'Edg/') => 'Edge',
        str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
        str_contains($ua, 'Firefox') || str_contains($ua, 'FxiOS') => 'Firefox',
        str_contains($ua, 'CriOS') || str_contains($ua, 'Chrome') => 'Chrome',
        str_contains($ua, 'Safari') => 'Safari',
        default => '',
    };
    return $browser !== '' ? "{$device} · {$browser}" : $device;
}

/**
 * Every voter in a contest with their picks and duplicate-vote flags.
 * Flag levels: 'high' (very likely the same person), 'medium' (worth a look), 'info'.
 */
function contest_voters(array $contest): array
{
    $id = (int) $contest['id'];
    $voters = db_all(
        'SELECT r.*, COUNT(v.id) AS picks, MIN(v.created_at) AS first_vote, MAX(v.updated_at) AS last_vote
         FROM voters r LEFT JOIN votes v ON v.voter_id = r.id
         WHERE r.contest_id = ? GROUP BY r.id ORDER BY r.created_at ASC, r.id ASC',
        [$id]
    );
    $flags = array_fill_keys(array_column($voters, 'id'), []);
    $byId = array_column($voters, null, 'id');

    // Same name on more than one device (exact, then first + last name only).
    $groups = ['exact' => [], 'loose' => []];
    foreach ($voters as $v) {
        $n = normalize_name($v['name']);
        $groups['exact'][$n][] = $v['id'];
        $groups['loose'][name_key_first_last($n)][] = $v['id'];
    }
    foreach ($groups['exact'] as $ids) {
        if (count($ids) > 1) {
            foreach ($ids as $vid) {
                $flags[$vid]['name'] = ['high', 'Same name on ' . count($ids) . ' devices'];
            }
        }
    }
    foreach ($groups['loose'] as $ids) {
        if (count($ids) > 1) {
            foreach ($ids as $vid) {
                $flags[$vid]['name'] ??= ['medium', 'Name very similar to ' . (count($ids) - 1) . ' other voter' . (count($ids) > 2 ? 's' : '')];
            }
        }
    }

    // Same browser and network on different devices: often one phone in private mode or with cookies cleared.
    $phones = [];
    foreach ($voters as $v) {
        if ($v['ip'] !== '' && $v['user_agent'] !== '') {
            $phones[$v['ip'] . '|' . $v['user_agent']][] = $v['id'];
        }
    }
    foreach ($phones as $ids) {
        if (count($ids) > 1) {
            foreach ($ids as $vid) {
                $flags[$vid]['phone'] = ['medium', 'Same browser and network as ' . (count($ids) - 1) . ' other device' . (count($ids) > 2 ? 's' : '') . ' (could be one phone)'];
            }
        }
    }

    // Bursts: several new voters from one network within a couple of minutes.
    $byIp = [];
    foreach ($voters as $v) {
        $byIp[$v['ip']][] = $v;
    }
    foreach ($byIp as $list) {
        $n = count($list);
        for ($i = 0, $j = 0; $i < $n; $i++) {
            $start = utc_timestamp($list[$i]['created_at']);
            while ($j < $n && utc_timestamp($list[$j]['created_at']) - $start <= BURST_WINDOW_SECONDS) {
                $j++;
            }
            if ($j - $i >= BURST_MIN_VOTERS) {
                for ($k = $i; $k < $j; $k++) {
                    $flags[$list[$k]['id']]['burst'] = ['medium', ($j - $i) . ' new voters from this network within 2 minutes'];
                }
            }
        }
    }

    // One-word names are hard to match to a person.
    foreach ($voters as $v) {
        if (!str_contains(normalize_name($v['name']), ' ')) {
            $flags[$v['id']]['short'] = ['info', 'Only one name given'];
        }
    }

    // Voted for an entry sent from the same device.
    $self = db_all(
        'SELECT DISTINCT r.id FROM votes v JOIN voters r ON r.id = v.voter_id JOIN entries e ON e.id = v.entry_id
         WHERE v.contest_id = ? AND e.device_id = r.device_id',
        [$id]
    );
    foreach ($self as $row) {
        $flags[(int) $row['id']]['self'] = ['info', 'Voted for their own entry'];
    }

    $order = ['high' => 0, 'medium' => 1, 'info' => 2];
    foreach ($voters as &$v) {
        $list = array_values($flags[$v['id']]);
        usort($list, fn($a, $b) => $order[$a[0]] <=> $order[$b[0]]);
        $v['flags'] = $list;
        $v['flag_level'] = $list[0][0] ?? null;
    }
    unset($v);
    return $voters;
}

/** Every vote with voter, category and entry names, for export. */
function contest_vote_rows(int $contestId): array
{
    return db_all(
        'SELECT r.name AS voter, r.device_id, r.voided, c.name AS category, e.name AS entry, e.title, v.created_at, v.updated_at
         FROM votes v
         JOIN voters r ON r.id = v.voter_id
         JOIN categories c ON c.id = v.category_id
         JOIN entries e ON e.id = v.entry_id
         WHERE v.contest_id = ? ORDER BY r.name, c.sort_order',
        [$contestId]
    );
}

/** Send rows as a CSV download that opens cleanly in Excel. */
function send_csv(string $filename, array $header, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8 names correctly
    $safe = fn($cell) => is_string($cell) && $cell !== '' && in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $cell : $cell;
    fputcsv($out, $header, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($out, array_map($safe, $row), ',', '"', '');
    }
    fclose($out);
    exit;
}
