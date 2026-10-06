<?php
declare(strict_types=1);

/**
 * Contest modes. Each one sets the wording, starter categories, event name and
 * whether the photobooth starts on. Colors live in public/assets/app.css under [data-mode].
 */
function modes(): array
{
    return [
        'halloween' => [
            'label' => 'Halloween',
            'title' => 'Halloween Costume Contest',
            'noun' => 'costume',
            'categories' => ['Scariest', 'Funniest', 'Most Creative'],
            'event_name' => 'Costume parade',
            'booth' => true,
        ],
        'holiday' => [
            'label' => 'Holiday party',
            'title' => 'Ugly Sweater Showdown',
            'noun' => 'sweater',
            'categories' => ['Ugliest', 'Most Festive', 'Best DIY'],
            'event_name' => 'Sweater walk',
            'booth' => true,
        ],
        'general' => [
            'label' => 'General',
            'title' => 'Chili Cook-Off',
            'noun' => 'entry',
            'categories' => ['Best Flavor', 'Most Original', 'Best Presentation'],
            'event_name' => 'Tasting',
            'booth' => false,
        ],
    ];
}

function mode(string $key): array
{
    return modes()[$key] ?? modes()['general'];
}
