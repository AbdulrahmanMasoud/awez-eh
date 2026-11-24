<?php

declare(strict_types=1);

use App\Support\Text\ArabicNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * pg_trgm's similarity of two texts, after both are normalized as search will store and query them.
 */
function normalizedSimilarity(string $typed, string $stored): float
{
    $normalizer = new ArabicNormalizer;

    return (float) DB::scalar('select similarity(?, ?)', [$normalizer->normalize($typed), $normalizer->normalize($stored)]);
}

it('matches a typo to the right word above the search threshold', function (string $typed, string $stored): void {
    expect(normalizedSimilarity($typed, $stored))->toBeGreaterThanOrEqual(config('awez.search.similarity_threshold'));
})->with([
    'a missing letter' => ['صيدلة', 'صيدلية'],
    'two letters swapped, the lowest measured (0.27)' => ['مسشتفى', 'مستشفى'],
    'a wrong letter' => ['سباق', 'سباك'],
    'a doubled letter' => ['كهرباائي', 'كهربائي'],
    'spelling and diacritics together' => ['شقه للايجار', 'شقّة للإيجار'],
]);

it('keeps unrelated words below the search threshold', function (string $typed, string $stored): void {
    expect(normalizedSimilarity($typed, $stored))->toBeLessThan(config('awez.search.similarity_threshold'));
})->with([
    'pharmacy and restaurant' => ['صيدلية', 'مطعم'],
    'plumber and electrician' => ['سباك', 'كهربائي'],
    'apartment and car' => ['شقة', 'سيارة'],
    'restaurant and hospital, the highest measured (0.09)' => ['مطعم', 'مستشفى'],
]);

it('scores normalized spellings of the same word as identical', function (): void {
    expect(normalizedSimilarity('صيدليّة', 'صيدليه'))->toBe(1.0);
});
