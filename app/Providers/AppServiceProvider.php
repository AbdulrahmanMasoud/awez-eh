<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Bookings\Models\Booking;
use App\Domains\CityGuide\Models\GuideEntry;
use App\Domains\CityGuide\Models\NewsItem;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use App\Domains\Geography\Models\City;
use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;
use App\Domains\Marketplace\Models\UsedItem;
use App\Domains\Media\Models\Media;
use App\Domains\Media\Models\PendingUpload;
use App\Domains\Media\Settings\MediaSettings;
use App\Domains\Places\Models\Place;
use App\Domains\Places\Models\PlaceCategory;
use App\Domains\Places\Models\PlaceItem;
use App\Domains\RealEstate\Models\Property;
use App\Domains\RealEstate\Models\PropertyAgreement;
use App\Domains\Requests\Models\RequestReply;
use App\Domains\Requests\Models\ServiceRequest;
use App\Domains\Reviews\Models\Review;
use App\Domains\Services\Models\Provider;
use App\Domains\Services\Models\Trade;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

final class AppServiceProvider extends ServiceProvider
{
    private const int MIN_PASSWORD_LENGTH = 12;

    private const int MAX_PASSWORD_LENGTH = 255;

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $production = $this->app->isProduction();

        // Lazy loading, silently dropped attributes and missing attributes throw while building.
        Model::shouldBeStrict(! $production);

        // Dates never change under the code that holds them (CLAUDE.md invariant 4).
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands($production);

        if ($production) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
        $this->enforceMorphMap();

        // Admins' passwords (residents have none): long rather than clever.
        Password::defaults(fn (): Password => Password::min(self::MIN_PASSWORD_LENGTH)->max(self::MAX_PASSWORD_LENGTH));

        // `composer dev` runs the queue worker and the logs: Sail already serves the app on
        // APP_PORT, and there is no frontend to build. The worker also takes the `media` queue,
        // where photos' sizes are made (production's worker needs the same list).
        DevCommands::artisan('queue:listen --queue=default,'.Config::string('awez.media.queue').' --tries=1 --timeout=0', 'queue');
        DevCommands::only('queue', 'logs');
    }

    /**
     * Polymorphic columns (Sanctum's tokenable, reportable, a lead's source, ...) store these short names, never
     * class names: moving a class does not break stored rows, and a model left out throws.
     */
    private function enforceMorphMap(): void
    {
        Relation::enforceMorphMap([
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
            // Media's owners (media.model_type), and media itself as the activity log's subject.
            'property_agreement' => PropertyAgreement::class,
            'place_item' => PlaceItem::class,
            'guide_entry' => GuideEntry::class,
            'news_item' => NewsItem::class,
            'pending_upload' => PendingUpload::class,
            'media' => Media::class,
            // Reference data, as the activity log's subjects.
            'city' => City::class,
            'district' => District::class,
            'trade' => Trade::class,
            'place_category' => PlaceCategory::class,
        ]);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            // The sanctum guard, so a resident sending a token is counted alone even on public routes.
            $user = $request->user('sanctum');

            if ($user instanceof User) {
                return Limit::perMinute(Config::integer('awez.rate_limits.api.per_user_per_minute'))
                    ->by('user:'.$user->id);
            }

            return Limit::perMinute(Config::integer('awez.rate_limits.api.per_ip_per_minute'))
                ->by('ip:'.$request->ip());
        });

        // Uploads cost a decode and two conversions each: a separate, smaller budget per account
        // (MediaSettings, photos and avatars together). Only signed-in routes use it.
        RateLimiter::for('uploads', function (Request $request): Limit {
            $user = $request->user();

            return Limit::perHour(resolve(MediaSettings::class)->uploads_per_hour)
                ->by('uploads:'.($user instanceof User ? $user->id : $request->ip()));
        });
    }
}
