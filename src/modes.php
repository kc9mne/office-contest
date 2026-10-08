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
            'background' => 'assets/bg/halloween.webp',
            'booth' => true,
            'booth_verb' => 'Make it spooky',
            'booth_styles' => [
                ['name' => 'Zombie', 'from' => '#24361F', 'to' => '#7E9A4E',
                 'prompt' => 'Turn this person into a friendly cartoon zombie with green skin and torn office clothes, in a foggy graveyard at night. Spooky but fun, not gory.'],
                ['name' => 'Vampire', 'from' => '#22070F', 'to' => '#8C1C2E',
                 'prompt' => 'Make this person a classic vampire with a high-collared black cape, pale skin and a moonlit castle behind them.'],
                ['name' => 'Haunted portrait', 'from' => '#231C2C', 'to' => '#6B5A3A',
                 'prompt' => 'Paint this person as an old haunted oil portrait in an ornate gold frame, lit by candlelight, with a faint ghostly glow.'],
                ['name' => 'Pirate', 'from' => '#1B2A3A', 'to' => '#B8862B',
                 'prompt' => 'Make this person a swashbuckling pirate captain with a tricorn hat, long coat and gold trim, on the deck of a ship under a stormy sky.'],
                ['name' => 'Witch', 'from' => '#2B1840', 'to' => '#5E8C3A',
                 'prompt' => 'Make this person a classic witch with a tall pointed hat, a dark cloak and a bubbling cauldron, in a candle-lit cottage full of potion bottles.'],
                ['name' => 'Werewolf', 'from' => '#2A2622', 'to' => '#7A6A58',
                 'prompt' => 'Turn this person into a friendly cartoon werewolf with furry ears and fuzzy cheeks, howling-moon night sky behind them. Spooky but fun, not scary.'],
            ],
        ],
        'holiday' => [
            'label' => 'Holiday party',
            'title' => 'Ugly Sweater Showdown',
            'noun' => 'sweater',
            'categories' => ['Ugliest', 'Most Festive', 'Best DIY'],
            'event_name' => 'Sweater walk',
            'background' => null,
            'booth' => true,
            'booth_verb' => 'Make it festive',
            'booth_styles' => [
                ['name' => 'Elf', 'from' => '#1F6B47', 'to' => '#C0283A',
                 'prompt' => "Turn this person into one of Santa's elves with pointed ears, a green and red outfit and a busy toy workshop behind them."],
                ['name' => 'Snow globe', 'from' => '#7FB0DA', 'to' => '#DCEAF6',
                 'prompt' => 'Place this person inside a glass snow globe with gently falling snow and a tiny snowy village around them.'],
                ['name' => 'Ugly sweater', 'from' => '#A3202F', 'to' => '#D9A21B',
                 'prompt' => 'Dress this person in an over-the-top ugly Christmas sweater with reindeer and blinking lights, in a cozy living room with a decorated tree.'],
            ],
        ],
        'general' => [
            'label' => 'General',
            'title' => 'Chili Cook-Off',
            'noun' => 'entry',
            'categories' => ['Best Flavor', 'Most Original', 'Best Presentation'],
            'event_name' => 'Tasting',
            'background' => null,
            'booth' => false,
            'booth_verb' => 'Make it fun',
            'booth_styles' => [
                ['name' => 'Cartoon', 'from' => '#0E5A61', 'to' => '#3CC6D0',
                 'prompt' => 'Redraw this person as a bright, friendly cartoon character with bold outlines.'],
                ['name' => 'Comic book', 'from' => '#1B2A6B', 'to' => '#E2B100',
                 'prompt' => 'Redraw this person as a comic book hero panel with bold ink lines, halftone dots and a dramatic background.'],
                ['name' => 'Retro poster', 'from' => '#7A3B1D', 'to' => '#E39B4A',
                 'prompt' => 'Turn this photo into a 1960s screen-printed travel poster with flat, warm colors.'],
            ],
        ],
    ];
}

function mode(string $key): array
{
    return modes()[$key] ?? modes()['general'];
}
