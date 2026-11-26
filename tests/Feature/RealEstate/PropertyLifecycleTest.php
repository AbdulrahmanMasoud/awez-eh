<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\AdminRole;
use App\Domains\Identity\Enums\UserLocale;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AdminLog;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\Moderation\Exceptions\ActionNotAllowed;
use App\Domains\Moderation\Exceptions\ReasonRequired;
use App\Domains\Moderation\Support\ContentLog;
use App\Domains\RealEstate\Actions\ApproveProperty;
use App\Domains\RealEstate\Actions\ArchiveProperty;
use App\Domains\RealEstate\Actions\MarkPropertyRented;
use App\Domains\RealEstate\Actions\MarkPropertySold;
use App\Domains\RealEstate\Actions\PauseProperty;
use App\Domains\RealEstate\Actions\RejectProperty;
use App\Domains\RealEstate\Actions\ResumeProperty;
use App\Domains\RealEstate\Actions\SubmitProperty;
use App\Domains\RealEstate\Actions\TakeDownProperty;
use App\Domains\RealEstate\Enums\PropertyPurpose;
use App\Domains\RealEstate\Events\PropertyApproved;
use App\Domains\RealEstate\Events\PropertyArchived;
use App\Domains\RealEstate\Events\PropertyPaused;
use App\Domains\RealEstate\Events\PropertyRejected;
use App\Domains\RealEstate\Events\PropertyRented;
use App\Domains\RealEstate\Events\PropertyResumed;
use App\Domains\RealEstate\Events\PropertySold;
use App\Domains\RealEstate\Events\PropertySubmitted;
use App\Domains\RealEstate\Events\PropertyTakenDown;
use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Notifications\PropertyReviewed;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->owner = User::factory()->withPhone()->create();
    $this->moderator = User::factory()->admin(AdminRole::Moderator)->create();
});

it('sends a draft to review, then a moderator publishes it, stamping who and when', function (): void {
    Date::setTestNow('2026-11-27 09:00:00');
    $property = Property::factory()->for($this->owner)->inStatus(ContentStatus::Draft)->create();

    $property = app(SubmitProperty::class)->handle($property, $this->owner);
    expect($property->status)->toBe(ContentStatus::PendingReview);

    Date::setTestNow('2026-11-27 10:30:00');
    $property = app(ApproveProperty::class)->handle($property, $this->moderator);

    expect($property->status)->toBe(ContentStatus::Published)
        ->and($property->reviewed_by)->toBe($this->moderator->id)
        ->and($property->reviewed_at?->toDateTimeString())->toBe('2026-11-27 10:30:00')
        ->and($property->published_at?->toDateTimeString())->toBe('2026-11-27 10:30:00');
});

it('keeps the first publication date when a property is approved again after an edit', function (): void {
    $property = Property::factory()->for($this->owner)->published()->create();
    $first = $property->published_at?->toDateTimeString();
    $property->forceFill(['status' => ContentStatus::PendingReview])->save();

    $property = app(ApproveProperty::class)->handle($property, $this->moderator);

    expect($property->published_at?->toDateTimeString())->toBe($first);
});

it('rejects and takes down with a reason only, which the owner sees', function (): void {
    $pending = Property::factory()->for($this->owner)->create();

    expect(fn () => app(RejectProperty::class)->handle($pending, $this->moderator, '   '))->toThrow(ReasonRequired::class);

    $rejected = app(RejectProperty::class)->handle($pending, $this->moderator, 'الصور مش واضحة.');
    expect($rejected->status)->toBe(ContentStatus::Rejected)
        ->and($rejected->rejection_reason)->toBe('الصور مش واضحة.');

    $published = Property::factory()->for($this->owner)->published()->create();
    $takenDown = app(TakeDownProperty::class)->handle($published, $this->moderator, 'الإعلان مكرر.');

    expect($takenDown->status)->toBe(ContentStatus::Rejected)
        ->and($takenDown->rejection_reason)->toBe('الإعلان مكرر.');
});

it('lets a rejected property go back to review only after its owner changed it', function (): void {
    $property = Property::factory()->for($this->owner)->rejected()->create();

    expect(fn () => app(SubmitProperty::class)->handle($property, $this->owner))->toThrow(ActionNotAllowed::class);

    $property->forceFill(['changed_since_review' => true])->save();
    $property = app(SubmitProperty::class)->handle($property, $this->owner);

    expect($property->status)->toBe(ContentStatus::PendingReview)
        ->and($property->changed_since_review)->toBeFalse();
});

it('resumes a paused property straight to the public, or through review when it was edited while paused', function (): void {
    $property = Property::factory()->for($this->owner)->published()->create();

    $property = app(PauseProperty::class)->handle($property, $this->owner);
    expect($property->status)->toBe(ContentStatus::Paused);

    $property = app(ResumeProperty::class)->handle($property, $this->owner);
    expect($property->status)->toBe(ContentStatus::Published);

    $property = app(PauseProperty::class)->handle($property, $this->owner);
    $property->forceFill(['changed_since_review' => true])->save();
    $property = app(ResumeProperty::class)->handle($property, $this->owner);

    expect($property->status)->toBe(ContentStatus::PendingReview)
        ->and($property->changed_since_review)->toBeFalse();
});

it('ends a sale as sold and a rent as rented, never the other way', function (): void {
    $sale = Property::factory()->for($this->owner)->published()->state(['purpose' => PropertyPurpose::Sale, 'rent_period' => null])->create();
    $rent = Property::factory()->for($this->owner)->forRent()->paused()->create();

    expect(fn () => app(MarkPropertyRented::class)->handle($sale, $this->owner))->toThrow(ActionNotAllowed::class)
        ->and(fn () => app(MarkPropertySold::class)->handle($rent, $this->owner))->toThrow(ActionNotAllowed::class)
        ->and(app(MarkPropertySold::class)->handle($sale, $this->owner)->status)->toBe(ContentStatus::Sold)
        ->and(app(MarkPropertyRented::class)->handle($rent, $this->owner)->status)->toBe(ContentStatus::Rented)
        ->and(fn () => app(ArchiveProperty::class)->handle($sale, $this->owner))->toThrow(ActionNotAllowed::class);
});

it('tells the caller what is allowed instead when a move is refused', function (): void {
    $property = Property::factory()->for($this->owner)->published()->state(['purpose' => PropertyPurpose::Sale, 'rent_period' => null])->create();

    try {
        app(SubmitProperty::class)->handle($property, $this->owner);
        $this->fail('A published property is not submitted.');
    } catch (ActionNotAllowed $exception) {
        expect($exception->errors())->toBe(['allowed_actions' => ['pause', 'mark_sold', 'archive']])
            ->and($exception->status)->toBe(409);
    }
});

it('logs moderators in the admin log and owners in the content log, with the statuses before and after', function (): void {
    $property = Property::factory()->for($this->owner)->create();

    app(RejectProperty::class)->handle($property, $this->moderator, 'البيانات ناقصة.');
    $property->forceFill(['changed_since_review' => true])->save();
    app(SubmitProperty::class)->handle($property, $this->owner);

    $admin = Activity::query()->where('log_name', AdminLog::NAME)->sole();
    $owner = Activity::query()->where('log_name', ContentLog::NAME)->sole();

    expect($admin->event)->toBe('reject')
        ->and($admin->causer?->is($this->moderator))->toBeTrue()
        ->and($admin->subject?->is($property))->toBeTrue()
        ->and($admin->properties->get('reason'))->toBe('البيانات ناقصة.')
        ->and($admin->attribute_changes?->toArray())->toBe(['old' => ['status' => 'pending_review'], 'attributes' => ['status' => 'rejected']])
        ->and($owner->event)->toBe('submit')
        ->and($owner->causer?->is($this->owner))->toBeTrue()
        ->and($owner->attribute_changes?->toArray())->toBe(['old' => ['status' => 'rejected'], 'attributes' => ['status' => 'pending_review']]);
});

it('dispatches one event per move, only once the transaction commits', function (): void {
    Event::fake([PropertySubmitted::class, PropertyApproved::class, PropertyRejected::class, PropertyTakenDown::class, PropertyPaused::class, PropertyResumed::class, PropertySold::class, PropertyRented::class, PropertyArchived::class]);
    $property = Property::factory()->for($this->owner)->inStatus(ContentStatus::Draft)->state(['purpose' => PropertyPurpose::Sale, 'rent_period' => null])->create();

    DB::transaction(function () use ($property): void {
        app(SubmitProperty::class)->handle($property, $this->owner);
        Event::assertNotDispatched(PropertySubmitted::class);
    });
    Event::assertDispatched(PropertySubmitted::class, fn (PropertySubmitted $event): bool => $event->propertyId === $property->id
        && $event->from === ContentStatus::Draft && $event->to === ContentStatus::PendingReview);

    app(ApproveProperty::class)->handle($property, $this->moderator);
    app(PauseProperty::class)->handle($property, $this->owner);
    app(ResumeProperty::class)->handle($property, $this->owner);
    app(TakeDownProperty::class)->handle($property, $this->moderator, 'مكرر.');
    $other = Property::factory()->for($this->owner)->create();
    app(RejectProperty::class)->handle($other, $this->moderator, 'ناقص.');
    $sale = Property::factory()->for($this->owner)->published()->state(['purpose' => PropertyPurpose::Sale, 'rent_period' => null])->create();
    app(MarkPropertySold::class)->handle($sale, $this->owner);
    $rent = Property::factory()->for($this->owner)->forRent()->published()->create();
    app(MarkPropertyRented::class)->handle($rent, $this->owner);
    app(ArchiveProperty::class)->handle($other, $this->owner);

    Event::assertDispatchedTimes(PropertyApproved::class, 1);
    Event::assertDispatchedTimes(PropertyPaused::class, 1);
    Event::assertDispatchedTimes(PropertyResumed::class, 1);
    Event::assertDispatched(PropertyTakenDown::class, fn (PropertyTakenDown $event): bool => $event->reason === 'مكرر.');
    Event::assertDispatchedTimes(PropertyRejected::class, 1);
    Event::assertDispatchedTimes(PropertySold::class, 1);
    Event::assertDispatchedTimes(PropertyRented::class, 1);
    Event::assertDispatchedTimes(PropertyArchived::class, 1);
});

it('tells the owner of each review in their own language, with the reason', function (): void {
    $english = User::factory()->withPhone()->create(['locale' => UserLocale::English]);
    $approved = Property::factory()->for($this->owner)->create(['title' => 'شقة للبيع في الحي الأول']);
    $rejected = Property::factory()->for($english)->create(['title' => 'Flat near the university']);
    $takenDown = Property::factory()->for($this->owner)->published()->create(['title' => 'محل للإيجار']);

    app(ApproveProperty::class)->handle($approved, $this->moderator);
    app(RejectProperty::class)->handle($rejected, $this->moderator, 'Photos are blurry.');
    app(TakeDownProperty::class)->handle($takenDown, $this->moderator, 'مكرر.');

    $notes = DatabaseNotification::query()->orderBy('created_at')->get();

    expect($notes)->toHaveCount(3)
        ->and($notes->pluck('type')->unique()->all())->toBe([PropertyReviewed::class])
        // jsonb keeps its own key order.
        ->and($notes->firstWhere('data.property_id', $approved->id)?->data)->toEqual([
            'type' => 'property_approve',
            'property_id' => $approved->id,
            'title' => 'إعلانك اتنشر',
            'body' => 'عقارك «شقة للبيع في الحي الأول» بقى ظاهر للناس.',
            'reason' => null,
        ])
        ->and($notes->firstWhere('data.property_id', $rejected->id)?->data)->toMatchArray([
            'type' => 'property_reject',
            'title' => 'Your listing needs changes',
            'reason' => 'Photos are blurry.',
        ])
        ->and($notes->firstWhere('data.property_id', $rejected->id)?->notifiable_id)->toBe($english->id)
        ->and($notes->firstWhere('data.property_id', $takenDown->id)?->data)->toMatchArray([
            'type' => 'property_take_down',
            'reason' => 'مكرر.',
        ]);
});

it('notifies nobody of an owner\'s own moves', function (): void {
    $property = Property::factory()->for($this->owner)->published()->create();

    app(PauseProperty::class)->handle($property, $this->owner);
    app(ArchiveProperty::class)->handle($property, $this->owner);

    expect(DatabaseNotification::query()->count())->toBe(0);
});
