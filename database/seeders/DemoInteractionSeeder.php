<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Alerts\Enums\AlertFrequency;
use App\Domains\Alerts\Models\SearchAlert;
use App\Domains\Bookings\Enums\BookingActor;
use App\Domains\Bookings\Enums\BookingKind;
use App\Domains\Bookings\Enums\BookingStatus;
use App\Domains\Bookings\Models\Booking;
use App\Domains\Bookings\Models\BookingEvent;
use App\Domains\Bookings\Support\BookingStateMachine;
use App\Domains\CityGuide\Models\DutyPharmacy;
use App\Domains\CityGuide\Models\NewsItem;
use App\Domains\Commissions\Enums\CommissionEntryKind;
use App\Domains\Commissions\Enums\LeadChannel;
use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Commissions\Models\Lead;
use App\Domains\Conversations\Enums\MessageKind;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use App\Domains\Favorites\Models\Favorite;
use App\Domains\Geography\Models\City;
use App\Domains\Identity\Models\User;
use App\Domains\Marketplace\Models\UsedItem;
use App\Domains\Moderation\Enums\ContentStatus;
use App\Domains\Places\Models\Place;
use App\Domains\Promotions\Enums\FeaturableType;
use App\Domains\Promotions\Models\FeaturedPackage;
use App\Domains\Promotions\Models\FeaturedPlacement;
use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Models\PropertyAgreement;
use App\Domains\Requests\Enums\RequestArea;
use App\Domains\Requests\Models\RequestReply;
use App\Domains\Reviews\Models\Review;
use App\Domains\Services\Models\Provider;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Database\Factories\Support\Random;
use Database\Factories\Support\SadatCity;
use Faker\Generator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use LogicException;

/**
 * Made-up conversations, bookings, reviews, favorites, alerts, leads, commissions, featured
 * listings, duty pharmacies and news, on top of DemoContentSeeder's city. Local and testing only.
 *
 * Every booking's history is walked through BookingStateMachine, so the demo never holds a
 * transition the rules refuse. Every brokered conversation and viewing has its lead.
 */
final class DemoInteractionSeeder extends Seeder
{
    /**
     * The statuses the service visits end in, and the path that reaches each, as (status, who) steps
     * after the request. "Counterpart" is the craftsman.
     *
     * @var list<list<array{BookingStatus, BookingActor}>>
     */
    private const array VISIT_PATHS = [
        [],
        [],
        [[BookingStatus::Confirmed, BookingActor::Counterpart]],
        [[BookingStatus::Confirmed, BookingActor::Counterpart]],
        [[BookingStatus::Declined, BookingActor::Counterpart]],
        [[BookingStatus::Declined, BookingActor::System]],
        [[BookingStatus::Confirmed, BookingActor::Counterpart], [BookingStatus::OnTheWay, BookingActor::Counterpart]],
        [[BookingStatus::Confirmed, BookingActor::Counterpart], [BookingStatus::OnTheWay, BookingActor::Counterpart], [BookingStatus::InProgress, BookingActor::Counterpart]],
        [[BookingStatus::Confirmed, BookingActor::Counterpart], [BookingStatus::OnTheWay, BookingActor::Counterpart], [BookingStatus::InProgress, BookingActor::Counterpart], [BookingStatus::Completed, BookingActor::Counterpart]],
        [[BookingStatus::Confirmed, BookingActor::Counterpart], [BookingStatus::InProgress, BookingActor::Counterpart], [BookingStatus::Completed, BookingActor::Counterpart]],
        [[BookingStatus::Confirmed, BookingActor::Counterpart], [BookingStatus::OnTheWay, BookingActor::Counterpart], [BookingStatus::InProgress, BookingActor::Counterpart], [BookingStatus::Completed, BookingActor::Counterpart]],
        [[BookingStatus::Confirmed, BookingActor::Counterpart], [BookingStatus::CancelledByResident, BookingActor::Resident]],
        [[BookingStatus::Confirmed, BookingActor::Counterpart], [BookingStatus::OnTheWay, BookingActor::Counterpart], [BookingStatus::CancelledByCounterpart, BookingActor::Counterpart]],
        [[BookingStatus::Confirmed, BookingActor::Counterpart], [BookingStatus::OnTheWay, BookingActor::Counterpart], [BookingStatus::NoShow, BookingActor::Counterpart]],
    ];

    /**
     * Viewings of brokered properties, which the platform's staff run.
     *
     * @var list<list<array{BookingStatus, BookingActor}>>
     */
    private const array BROKERED_VIEWING_PATHS = [
        [],
        [[BookingStatus::Confirmed, BookingActor::Platform]],
        [[BookingStatus::Confirmed, BookingActor::Platform]],
        [[BookingStatus::Confirmed, BookingActor::Platform], [BookingStatus::Completed, BookingActor::Platform]],
        [[BookingStatus::Confirmed, BookingActor::Platform], [BookingStatus::Completed, BookingActor::Platform]],
        [[BookingStatus::Confirmed, BookingActor::Platform], [BookingStatus::NoShow, BookingActor::Platform]],
        [[BookingStatus::CancelledByResident, BookingActor::Resident]],
    ];

    /**
     * Viewings run by the owner of a property the platform does not broker.
     *
     * @var list<list<array{BookingStatus, BookingActor}>>
     */
    private const array OWNER_VIEWING_PATHS = [
        [],
        [[BookingStatus::Confirmed, BookingActor::Counterpart]],
        [[BookingStatus::Confirmed, BookingActor::Counterpart], [BookingStatus::Completed, BookingActor::Counterpart]],
        [[BookingStatus::Declined, BookingActor::Counterpart]],
        [[BookingStatus::Confirmed, BookingActor::Counterpart], [BookingStatus::CancelledByCounterpart, BookingActor::Counterpart]],
    ];

    /** What residents write first, and what the other side answers, by kind of subject. */
    private const array MESSAGES = [
        'property' => [['السلام عليكم، الشقة لسه متاحة؟', 'متاحة، تحب تحدد ميعاد معاينة؟'], ['السعر فيه فصال؟', 'في حدود بسيطة بعد المعاينة.']],
        'provider' => [['محتاج تغيير خلاط مطبخ، تقدر تيجي بكرة؟', 'تمام، الساعة ٥ العصر مناسبة؟'], ['بتشتغل يوم الجمعة؟', 'أيوه، من بعد الصلاة.']],
        'place' => [['بتوصلوا السكنية السابعة؟', 'أيوه، التوصيل ١٥ جنيه.'], ['فاتحين لحد إمتى النهاردة؟', 'لحد ٢ بالليل.']],
        'used_item' => [['الغسالة لسه موجودة؟', 'موجودة، تحب تشوفها؟'], ['آخر سعر كام؟', 'هنزلك ٢٠٠ جنيه.']],
        'request_reply' => [['شفت ردك على طلبي، ممكن تفاصيل أكتر؟', 'أكيد، أبعتلك الصور هنا.']],
    ];

    private Generator $faker;

    private BookingStateMachine $machine;

    private User $staff;

    /** @var Collection<int, User> */
    private Collection $residents;

    /** @var list<Lead> */
    private array $brokeredLeads = [];

    public function run(): void
    {
        if (! App::environment(DemoContentSeeder::ENVIRONMENTS)) {
            throw new LogicException('Demo content is made up: it is seeded in local and testing only.');
        }

        $this->faker = fake();
        $this->machine = new BookingStateMachine;
        // Answers for the platform: brokered conversations and viewings. Admin roles come in step 1.5.
        $this->staff = User::factory()->create(['name' => 'فريق أوز إيه', 'email' => 'staff@awez-eh.test']);
        $this->residents = User::query()->whereDoesntHave('provider')->whereKeyNot($this->staff->id)->get();

        $this->seedConversations();
        $this->seedBookings();
        $this->voidOneLead();
        $this->seedFavoritesAndAlerts();
        $this->seedCommissions();
        $this->seedPromotions();
        $this->seedCityGuide();
    }

    private function seedConversations(): void
    {
        $agreementProperty = PropertyAgreement::query()->sole()->property ?? throw new LogicException('The demo has an agreement.');
        $brokered = Property::query()->where('brokered', true)->where('status', ContentStatus::Published)->whereKeyNot($agreementProperty->id)->limit(4)->get();

        foreach ([$agreementProperty, ...$brokered] as $property) {
            foreach ($this->residentsOtherThan($property->user_id, 2) as $resident) {
                $conversation = $this->converse($property, $resident, null);
                $this->brokeredLeads[] = $this->lead($property, $resident, LeadChannel::Chat, $conversation);
            }
        }

        $subjects = [
            ...Property::query()->where('brokered', false)->where('status', ContentStatus::Published)->limit(5)->get(),
            ...Provider::query()->where('status', ContentStatus::Published)->limit(5)->get(),
            ...Place::query()->whereNotNull('user_id')->where('status', ContentStatus::Published)->limit(3)->get(),
            ...UsedItem::query()->where('status', ContentStatus::Published)->limit(4)->get(),
        ];

        foreach ($subjects as $subject) {
            $owner = $subject->getAttribute('user_id');
            $this->converse($subject, $this->residentsOtherThan(is_string($owner) ? $owner : null, 1)->sole(), $owner);
        }

        // A resident talks to someone who answered their request.
        foreach (RequestReply::query()->with('request.user')->limit(3)->get() as $reply) {
            $this->converse($reply, $reply->request->user ?? throw new LogicException('A request has a resident.'), $reply->user_id);
        }
    }

    /**
     * A conversation with a few messages, the last one sharing a location now and then.
     */
    private function converse(Model $subject, User $resident, mixed $counterpartId): Conversation
    {
        $at = CarbonImmutable::now()->subDays($this->faker->numberBetween(1, 20))->startOfMinute();
        $exchanges = self::MESSAGES[$subject->getMorphClass()];
        $counterpart = is_string($counterpartId) ? $counterpartId : $this->staff->id;

        $conversation = Conversation::factory()->about($subject)->for($resident, 'resident')->create([
            'counterpart_id' => is_string($counterpartId) ? $counterpartId : null,
            'last_message_at' => $at,
        ]);

        foreach (Random::elements($this->faker, $exchanges, $this->faker->numberBetween(1, count($exchanges))) as [$question, $answer]) {
            $this->message($conversation, $resident->id, MessageKind::Text, $question, $at = $at->addMinutes($this->faker->numberBetween(1, 90)));
            $this->message($conversation, $counterpart, MessageKind::Text, $answer, $at = $at->addMinutes($this->faker->numberBetween(1, 90)));
        }

        if ($this->faker->boolean(25)) {
            $this->message($conversation, $resident->id, MessageKind::Location, null, $at = $at->addMinutes(5));
        }

        $conversation->forceFill(['last_message_at' => $at, 'resident_read_at' => $at])->save();

        return $conversation;
    }

    private function message(Conversation $conversation, string $senderId, MessageKind $kind, ?string $body, CarbonImmutable $at): void
    {
        Message::factory()->for($conversation)->create([
            'sender_id' => $senderId,
            'kind' => $kind,
            'body' => $body,
            'location' => $kind === MessageKind::Location ? SadatCity::randomPoint($this->faker) : null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function seedBookings(): void
    {
        $providers = Provider::query()->where('status', ContentStatus::Published)->get()->all();

        foreach (self::VISIT_PATHS as $index => $path) {
            $provider = $providers[$index % count($providers)];
            $this->book($provider, $this->residentsOtherThan($provider->user_id, 1)->sole(), $path);
        }

        $brokered = Property::query()->where('brokered', true)->where('status', ContentStatus::Published)->get()->all();

        foreach (self::BROKERED_VIEWING_PATHS as $index => $path) {
            $property = $brokered[$index % count($brokered)];
            $resident = $this->residentsOtherThan($property->user_id, 1)->sole();
            $booking = $this->book($property, $resident, $path);
            // Every viewing booked on a brokered listing is a lead (invariant 2).
            $this->brokeredLeads[] = $this->lead($property, $resident, LeadChannel::Viewing, $booking);
        }

        $owned = Property::query()->where('brokered', false)->where('status', ContentStatus::Published)->get()->all();

        foreach (self::OWNER_VIEWING_PATHS as $index => $path) {
            $property = $owned[$index % count($owned)];
            $this->book($property, $this->residentsOtherThan($property->user_id, 1)->sole(), $path);
        }

        // A message when a booking is confirmed, in a conversation the system writes to.
        $confirmed = Booking::query()->where('status', BookingStatus::Confirmed)->where('kind', BookingKind::ServiceVisit)->first();

        if ($confirmed instanceof Booking && $confirmed->bookable instanceof Provider) {
            $conversation = $this->converse($confirmed->bookable, $confirmed->resident ?? throw new LogicException('A booking has a resident.'), $confirmed->counterpart_id);
            $this->message($conversation, $this->staff->id, MessageKind::System, 'تم تأكيد الحجز.', CarbonImmutable::now()->subMinutes(30));
        }
    }

    /**
     * A booking and its events: the request, then each step of the path, each one checked by the
     * state machine. A completed booking gets a review.
     *
     * @param  list<array{BookingStatus, BookingActor}>  $path
     */
    private function book(Property|Provider $bookable, User $resident, array $path): Booking
    {
        $finished = $path !== [] && $this->machine->isTerminal(array_last($path)[0]);
        $scheduledAt = CarbonImmutable::now()->{$finished ? 'subDays' : 'addDays'}($this->faker->numberBetween(1, 10))->setTime($this->faker->numberBetween(9, 20), 0);
        $at = $scheduledAt->subDays(3);

        $booking = Booking::factory()->of($bookable)->for($resident, 'resident')->create([
            'scheduled_at' => $scheduledAt,
            'address_note' => $bookable instanceof Provider ? Random::element($this->faker, ['الدور التالت شقة ٧', 'فيلا ١٢ جنب المسجد', 'عمارة ٤ مدخل ب']) : null,
            'estimated_price' => $bookable instanceof Provider ? Money::of($this->faker->numberBetween(2, 10) * 100, 'EGP') : null,
            'created_at' => $at,
        ]);

        $this->event($booking, null, BookingStatus::Requested, $resident->id, $at);
        $status = BookingStatus::Requested;

        foreach ($path as [$next, $actor]) {
            if (! $this->machine->canTransition($booking->kind, $status, $next, $actor)) {
                throw new LogicException("The demo path from {$status->value} to {$next->value} by {$actor->value} is not allowed.");
            }

            $this->event($booking, $status, $next, $this->actorId($booking, $actor), $at = $at->addHours($this->faker->numberBetween(1, 30)));
            $status = $next;
        }

        $booking->forceFill(['status' => $status])->save();

        if ($status === BookingStatus::Completed) {
            $this->review($booking, $resident);
        }

        return $booking;
    }

    private function event(Booking $booking, ?BookingStatus $from, BookingStatus $to, ?string $actorId, CarbonImmutable $at): void
    {
        BookingEvent::factory()->for($booking)->create(['from_status' => $from, 'to_status' => $to, 'actor_id' => $actorId, 'created_at' => $at]);
    }

    private function actorId(Booking $booking, BookingActor $actor): ?string
    {
        return match ($actor) {
            BookingActor::Resident => $booking->resident_id,
            BookingActor::Counterpart => $booking->counterpart_id ?? throw new LogicException('A brokered booking has no counterpart.'),
            BookingActor::Platform => $this->staff->id,
            BookingActor::System => null,
        };
    }

    private function review(Booking $booking, User $resident): void
    {
        $review = Review::factory()->for($booking)->for($resident, 'reviewer')->create([
            'reviewable_type' => $booking->bookable_type,
            'reviewable_id' => $booking->bookable_id,
            'rating' => Random::element($this->faker, [5, 5, 4, 4, 3, 2]),
        ]);

        if ($review->rating === 2) {
            // An admin hid an offensive review: only its reviewer still sees it.
            $review->forceFill(['is_hidden' => true, 'hidden_by' => $this->staff->id, 'hidden_at' => CarbonImmutable::now()->subDay()])->save();
        }
    }

    private function lead(Property $property, User $resident, LeadChannel $channel, Conversation|Booking $source): Lead
    {
        return Lead::factory()->create([
            'property_id' => $property->id,
            'resident_id' => $resident->id,
            'channel' => $channel,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'created_at' => $source->getAttribute('created_at'),
        ]);
    }

    /** A resident opened a chat by mistake: a correcting row voids the lead, which stays. */
    private function voidOneLead(): void
    {
        $lead = array_find($this->brokeredLeads, fn (Lead $lead): bool => $lead->channel === LeadChannel::Chat)
            ?? throw new LogicException('The demo has chat leads.');

        Lead::factory()->voiding($lead)->create(['void_reason' => 'الساكن أكد إنه فتح المحادثة بالغلط.']);
    }

    private function seedFavoritesAndAlerts(): void
    {
        $content = [
            ...Property::query()->where('status', ContentStatus::Published)->get(),
            ...Provider::query()->where('status', ContentStatus::Published)->get(),
            ...Place::query()->where('status', ContentStatus::Published)->get(),
            ...UsedItem::query()->where('status', ContentStatus::Published)->get(),
        ];

        foreach ($this->residents as $resident) {
            foreach (Random::elements($this->faker, $content, $this->faker->numberBetween(0, 3)) as $item) {
                Favorite::factory()->for($resident)->create(['favoritable_type' => $item->getMorphClass(), 'favoritable_id' => $item->getKey()]);
            }
        }

        $districts = City::query()->where('slug', 'sadat-city')->sole()->districts()->get();
        $alerts = [
            [RequestArea::RealEstate, 'شقة إيجار ٣ غرف', AlertFrequency::Instant, true],
            [RequestArea::RealEstate, 'دوبلكس للبيع', AlertFrequency::Daily, false],
            [RequestArea::Marketplace, 'غسالة', AlertFrequency::Instant, true],
            [RequestArea::Marketplace, 'لابتوب', AlertFrequency::Daily, false],
            [RequestArea::Services, 'سباك', AlertFrequency::Instant, true],
            [RequestArea::Places, null, AlertFrequency::Daily, true],
        ];

        foreach ($alerts as [$area, $query, $frequency, $nearby]) {
            SearchAlert::factory()->for($this->residents->random())->create([
                'area' => $area,
                'query' => $query,
                'frequency' => $frequency,
                'district_id' => $nearby ? null : $districts->random()->id,
                'location' => $nearby ? SadatCity::randomPoint($this->faker, 2000) : null,
                'radius_m' => $nearby ? 1500 : null,
            ]);
        }
    }

    /** The agreement's property sold: the commission is expected, invoiced, and partly collected. */
    private function seedCommissions(): void
    {
        $agreement = PropertyAgreement::query()->sole();
        $lead = Lead::query()->where('property_id', $agreement->property_id)->whereNull('voids_lead_id')->firstOrFail();
        $deal = Money::of(1_800_000, 'EGP');
        $commission = $deal->multipliedBy($agreement->commission_rate_basis_points ?? 200)->dividedBy(10_000);
        $day = CarbonImmutable::today()->subDays(12);

        foreach ([
            [CommissionEntryKind::Expected, $commission, 'العميل اتفق على السعر.'],
            [CommissionEntryKind::Invoiced, $commission, 'فاتورة العمولة بعد توقيع العقد.'],
            [CommissionEntryKind::Collected, $commission->dividedBy(2), 'الدفعة الأولى.'],
        ] as $index => [$kind, $amount, $note]) {
            CommissionEntry::factory()->for($agreement, 'agreement')->for($lead)->create([
                'property_id' => $agreement->property_id,
                'kind' => $kind,
                'amount' => $amount,
                'deal_price' => $deal,
                'occurred_on' => $day->addDays(4 * $index),
                'recorded_by' => $this->staff->id,
                'note' => $note,
            ]);
        }
    }

    /** Demo prices: the real ones are still open (OPEN_QUESTIONS 2). */
    private function seedPromotions(): void
    {
        $week = FeaturedPackage::factory()->create();
        FeaturedPackage::factory()->create([
            'name_ar' => 'تمييز شهر للحرفيين',
            'name_en' => 'Featured craftsman for a month',
            'applies_to' => FeaturableType::Provider,
            'duration_days' => 30,
            'price' => Money::of(400, 'EGP'),
            'sort_order' => 2,
        ]);

        $properties = Property::query()->with('user')->where('status', ContentStatus::Published)->where('brokered', false)->limit(3)->get();

        foreach ([null, 'ended', 'cancelled'] as $index => $state) {
            $property = $properties[$index];
            $factory = FeaturedPlacement::factory()->for($week, 'package')->for($property->user ?? throw new LogicException('A property has an owner.'))->state([
                'featurable_type' => $property->getMorphClass(),
                'featurable_id' => $property->id,
            ]);

            ($state === null ? $factory : $factory->{$state}())->create();
        }

        // Granted by an admin, without a package or a price.
        $provider = Provider::query()->where('status', ContentStatus::Published)->firstOrFail();
        FeaturedPlacement::factory()->for($provider->user ?? throw new LogicException('A provider has an account.'))->create([
            'featurable_type' => $provider->getMorphClass(),
            'featurable_id' => $provider->id,
            'featured_package_id' => null,
            'price_paid' => null,
            'granted_by' => $this->staff->id,
        ]);
    }

    private function seedCityGuide(): void
    {
        $pharmacies = Place::query()->where('status', ContentStatus::Published)->whereHas('category', fn ($query) => $query->where('slug', 'pharmacies'))->get()->all();
        $today = CarbonImmutable::now('Africa/Cairo')->startOfDay();

        // Tonight and the next 13 nights, from 20:00 to 08:00 the next morning, Cairo time.
        foreach (range(0, 13) as $day) {
            $night = $today->addDays($day);
            DutyPharmacy::factory()->for($pharmacies[$day % count($pharmacies)])->create([
                'duty_date' => $night->toDateString(),
                'starts_at' => $night->setTime(20, 0)->utc(),
                'ends_at' => $night->addDay()->setTime(8, 0)->utc(),
            ]);
        }

        $city = City::query()->where('slug', 'sadat-city')->sole();
        $news = [
            ['افتتاح سوق الجمعة الجديد في المنطقة السكنية الأولى', 'يفتح السوق أبوابه كل يوم جمعة من ٨ الصبح لحد العصر.', true],
            ['قطع المياه عن السكنية الرابعة يوم السبت للصيانة', 'من ٩ الصبح لحد ٣ العصر، والمياه هترجع تدريجيًا.', true],
            ['بدء التقديم في مدارس المدينة للعام الجديد', 'التقديم أونلاين على موقع المديرية لحد آخر الشهر.', true],
            ['تشغيل خط أتوبيس جديد للمنطقة الصناعية', 'الأتوبيس بيطلع كل نص ساعة من الموقف الرئيسي.', true],
            ['مهرجان المدينة السنوي', 'مسودة: المواعيد لسه هتتحدد.', false],
        ];

        foreach ($news as $index => [$title, $body, $published]) {
            NewsItem::factory()->for($city)->create([
                'title' => $title,
                'body' => $body,
                'is_published' => $published,
                'published_at' => $published ? CarbonImmutable::now()->subDays($index + 1) : null,
                'created_by' => $this->staff->id,
            ]);
        }
    }

    /**
     * Residents other than the given owner, picked at random.
     *
     * @return Collection<int, User>
     */
    private function residentsOtherThan(?string $ownerId, int $count): Collection
    {
        return $this->residents->reject(fn (User $resident): bool => $resident->id === $ownerId)->random($count);
    }
}
