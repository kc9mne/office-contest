<?php
declare(strict_types=1);

/** /tv: full-screen display for office TVs. Read-only. */
function page_tv(): void
{
    $contest = active_contest();
    $data = tv_data($contest);
    require APP_ROOT . '/src/views/tv.php';
}

/** /tv/data: the same data as JSON, polled by the TV page so it updates without reloading. */
function page_tv_data(): void
{
    json_response(tv_data(active_contest()));
}

function tv_data(?array $contest): array
{
    if (!$contest) {
        return ['contest' => null];
    }
    $id = (int) $contest['id'];
    $phase = contest_phase($contest);
    $standings = contest_standings($contest);
    $showResults = $phase === 'closed' || ($phase === 'live' && $contest['show_counts']);
    $entryRow = fn(array $r) => [
        'name' => $r['entry']['name'],
        'title' => $r['entry']['title'],
        'photo' => media_url($r['entry']['photo_path']),
        'votes' => $r['votes'],
        'place' => place_label($r),
    ];

    $top = [];
    $leaders = [];
    if ($showResults) {
        $top = array_map($entryRow, array_slice(array_values(array_filter($standings['overall'], fn($r) => $r['votes'] > 0)), 0, 3));
        foreach ($standings['categories'] as $cat) {
            $lead = ranked_leaders($cat['ranked']);
            $leaders[] = [
                'category' => $cat['category']['name'],
                'names' => implode(' & ', array_map(fn($r) => $r['entry']['name'], $lead)),
                'photo' => $lead ? media_url($lead[0]['entry']['photo_path']) : null,
                'votes' => $lead[0]['votes'] ?? 0,
                'tie' => count($lead) > 1,
            ];
        }
    }

    // Slideshow: photobooth pictures and entries, newest first.
    $slides = [];
    foreach (booth_gallery_photos($id, 60) as $p) {
        $isVideo = booth_photo_is_video($p);
        $slides[] = [
            'img' => media_url($isVideo ? $p['original_path'] : $p['result_path']),
            'video' => $isVideo ? media_url($p['result_path']) : null,
            'name' => $p['name'] !== '' ? $p['name'] : 'Photobooth',
            'caption' => $p['kind'] === 'strip' ? 'Photo strip' . ($p['style'] !== 'No AI' ? ' · ' . $p['style'] : '') : $p['style'],
            'wide' => $p['kind'] === 'group',
            'at' => $p['finished_at'] ?? $p['created_at'],
        ];
    }
    foreach ($standings['entries'] as $e) {
        $slides[] = ['img' => media_url($e['photo_path']), 'name' => $e['name'], 'caption' => $e['title'] !== '' ? $e['title'] : $e['department'], 'wide' => false, 'at' => $e['created_at']];
    }
    usort($slides, fn($a, $b) => strcmp($b['at'], $a['at']));

    return [
        'contest' => [
            'title' => $contest['title'],
            'subtitle' => $contest['subtitle'],
            'noun' => mode($contest['mode'])['noun'],
            'event' => trim($contest['event_name'] . ($contest['event_details'] !== '' ? ': ' . $contest['event_details'] : '')),
            'starts' => utc_to_local($contest['starts_at'], 'D M j, g:i A'),
            'ends' => utc_to_local($contest['ends_at'], 'D M j, g:i A'),
        ],
        'phase' => $phase,
        'phaseLabel' => phase_label($phase),
        'target' => utc_timestamp($phase === 'before' ? $contest['starts_at'] : $contest['ends_at']) * 1000,
        'showResults' => $showResults,
        'top' => $top,
        'leaders' => $leaders,
        'entries' => count($standings['entries']),
        'voters' => $standings['voters'],
        'slides' => array_slice($slides, 0, 80),
    ];
}
