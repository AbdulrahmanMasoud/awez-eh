<?php

declare(strict_types=1);

use App\Domains\Alerts\Models\SearchAlert;

it('keeps the normalized query beside the query', function (): void {
    $alert = SearchAlert::factory()->create(['query' => 'شقّة للإيجار']);

    expect(SearchAlert::query()->findOrFail($alert->id)->only(['query', 'search_text']))
        ->toBe(['query' => 'شقّة للإيجار', 'search_text' => 'شقه للايجار']);
});

it('has no search text without a query', function (): void {
    $alert = SearchAlert::factory()->create(['query' => null]);

    expect(SearchAlert::query()->findOrFail($alert->id)->search_text)->toBeNull();
});

it('starts with no filters', function (): void {
    $alert = SearchAlert::factory()->create();

    expect(SearchAlert::query()->findOrFail($alert->id)->filters)->toBe([]);
});
