<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The Lucide icons reference data may use (DECISIONS 44): the ones the seeder uses and the ones
 * trades and place categories are likely to need. Each name is Lucide's own, so the mobile app,
 * which ships Lucide, draws it. A new icon is added here.
 */
final class LucideIcons
{
    /** @var list<string> */
    public const array NAMES = [
        'anvil', 'app-window', 'baby', 'bath', 'bike', 'book-open', 'brick-wall', 'brush-cleaning',
        'building', 'building-2', 'car', 'coffee', 'cooking-pot', 'croissant', 'cross', 'dumbbell', 'flame',
        'flower', 'fuel', 'gift', 'graduation-cap', 'grid-2x2', 'hammer', 'hard-hat', 'heart-pulse', 'house', 'key',
        'landmark', 'laptop', 'lightbulb', 'paint-roller', 'paw-print', 'pill', 'pizza', 'plug',
        'scissors', 'shirt', 'shopping-basket', 'shopping-cart', 'smartphone', 'snowflake', 'soup',
        'sparkles', 'stethoscope', 'store', 'truck', 'tv', 'utensils', 'washing-machine', 'wrench', 'zap',
    ];
}
