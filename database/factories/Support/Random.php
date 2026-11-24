<?php

declare(strict_types=1);

namespace Database\Factories\Support;

use Faker\Generator;

/**
 * Faker's random picks, typed: what goes in is what comes out.
 */
final class Random
{
    /**
     * @template TItem
     *
     * @param  non-empty-list<TItem>  $items
     * @return TItem
     */
    public static function element(Generator $faker, array $items): mixed
    {
        return $items[$faker->numberBetween(0, count($items) - 1)];
    }

    /**
     * Distinct items, in random order.
     *
     * @template TItem
     *
     * @param  list<TItem>  $items
     * @return list<TItem>
     */
    public static function elements(Generator $faker, array $items, int $count): array
    {
        return array_slice(array_values($faker->shuffleArray($items)), 0, $count);
    }
}
