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
        'galleryCount' => $contest ? count(booth_gallery_photos((int) $contest['id'])) : 0,
    ]);
}

function page_gallery(): void
{
    $contest = active_contest();
    view('gallery', [
        'title' => 'Gallery',
        'contest' => $contest,
        'mode' => $contest['mode'] ?? 'general',
        'photos' => $contest ? booth_gallery_photos((int) $contest['id']) : [],
    ]);
}
