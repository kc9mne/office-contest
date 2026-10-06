<?php
declare(strict_types=1);

function page_home(): void
{
    $contest = active_contest();
    view('home', [
        'title' => $contest['title'] ?? company_name(),
        'contest' => $contest,
        'mode' => $contest['mode'] ?? 'general',
        'categories' => $contest ? contest_categories((int) $contest['id']) : [],
    ]);
}
