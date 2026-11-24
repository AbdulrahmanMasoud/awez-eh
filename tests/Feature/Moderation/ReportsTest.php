<?php

declare(strict_types=1);

use App\Domains\Bookings\Models\Booking;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use App\Domains\Geography\Models\City;
use App\Domains\Identity\Models\User;
use App\Domains\Marketplace\Models\UsedItem;
use App\Domains\Moderation\Enums\ReportStatus;
use App\Domains\Moderation\Models\Report;
use App\Domains\Places\Models\Place;
use App\Domains\RealEstate\Models\Property;
use App\Domains\Requests\Models\RequestReply;
use App\Domains\Requests\Models\ServiceRequest;
use App\Domains\Reviews\Models\Review;
use App\Domains\Services\Models\Provider;
use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('enforces a morph map with short names', function (): void {
    expect(Relation::requiresMorphMap())->toBeTrue()
        ->and(Relation::morphMap())->toBe([
            'user' => User::class,
            'property' => Property::class,
            'provider' => Provider::class,
            'place' => Place::class,
            'used_item' => UsedItem::class,
            'request' => ServiceRequest::class,
            'request_reply' => RequestReply::class,
            'conversation' => Conversation::class,
            'message' => Message::class,
            'booking' => Booking::class,
            'review' => Review::class,
        ]);
});

it('stores what a report points at by its short name and finds it back', function (string $model, string $name): void {
    /** @var class-string<Model> $model */
    $reported = $model::factory()->create();

    $report = Report::factory()->for($reported, 'reportable')->create();

    expect(DB::table('reports')->where('id', $report->id)->value('reportable_type'))->toBe($name)
        ->and(Report::query()->findOrFail($report->id)->reportable?->is($reported))->toBeTrue()
        ->and($reported->getAttribute('reports')->modelKeys())->toBe([$report->id]);
})->with([
    [Property::class, 'property'],
    [Provider::class, 'provider'],
    [Place::class, 'place'],
    [UsedItem::class, 'used_item'],
    [ServiceRequest::class, 'request'],
    [RequestReply::class, 'request_reply'],
    [Message::class, 'message'],
    [Review::class, 'review'],
]);

it('refuses to point at a model the morph map does not name', function (): void {
    expect(fn (): Report => Report::factory()->for(City::factory()->create(), 'reportable')->make())
        ->toThrow(ClassMorphViolationException::class);
});

it('takes one open report per resident and item', function (): void {
    $property = Property::factory()->create();
    $reporter = User::factory()->create();
    $first = Report::factory()->for($reporter)->for($property, 'reportable')->create();

    expect(fn () => DB::transaction(fn (): Report => Report::factory()->for($reporter)->for($property, 'reportable')->create()))
        ->toThrow(QueryException::class, 'reports_one_open_per_reporter_index');

    $first->forceFill(['status' => ReportStatus::Resolved, 'resolved_at' => now()])->save();

    expect(Report::factory()->for($reporter)->for($property, 'reportable')->create()->status)->toBe(ReportStatus::Open);
});
