<?php

declare(strict_types=1);

use App\Domains\Marketplace\Models\UsedItem;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\Places\Models\Place;
use App\Domains\RealEstate\Models\Property;
use App\Domains\Services\Models\Provider;
use Illuminate\Database\Eloquent\Model;

dataset('moderated models', [
    'property' => Property::class,
    'provider' => Provider::class,
    'place' => Place::class,
    'used item' => UsedItem::class,
]);

it('starts new content as pending review (invariant 9)', function (string $model): void {
    /** @var class-string<Model> $model */
    $created = $model::factory()->create();

    expect($created->getAttribute('status'))->toBe(ContentStatus::PendingReview)
        ->and($model::query()->findOrFail($created->getKey())->getAttribute('status'))->toBe(ContentStatus::PendingReview);
})->with('moderated models');

it('lists only published content in the published scope', function (string $model): void {
    /** @var class-string<Model> $model */
    $published = $model::factory()->published()->create();

    foreach (ContentStatus::cases() as $status) {
        if (! $status->isPublic()) {
            $model::factory()->create(['status' => $status]);
        }
    }

    expect($model::query()->published()->pluck('id')->all())->toBe([$published->getKey()]);
})->with('moderated models');

it('records who reviewed content and when it was published', function (string $model): void {
    /** @var class-string<Model> $model */
    $created = $model::factory()->published()->create();

    expect($created->getAttribute('published_at'))->not->toBeNull()
        ->and($created->getAttribute('reviewed_at'))->not->toBeNull();
})->with('moderated models');
