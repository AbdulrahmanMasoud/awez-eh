<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Geography\Models\District;
use App\Domains\Identity\Models\User;
use App\Domains\RealEstate\Actions\ShowRealEstateOptions;
use App\Domains\RealEstate\Events\PropertyApproved;
use App\Domains\RealEstate\Events\PropertyRejected;
use App\Domains\RealEstate\Events\PropertyTakenDown;
use App\Domains\RealEstate\Listeners\NotifyOwnerOfReview;
use App\Domains\RealEstate\Models\Property;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Real estate's wiring: the {property} route binding, the owner's notifications after a review, the limit on asking owners'
 * numbers, and the filters' cache, which goes whenever a district changes.
 */
final class RealEstateServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The owner's routes take the property from the request, not as a controller argument.
        Route::model('property', Property::class);

        foreach ([PropertyApproved::class, PropertyRejected::class, PropertyTakenDown::class] as $event) {
            Event::listen($event, NotifyOwnerOfReview::class);
        }

        RateLimiter::for('contact', function (Request $request): Limit {
            $user = $request->user();

            return Limit::perHour(Config::integer('awez.rate_limits.contact.per_user_per_hour'))
                ->by('contact:'.($user instanceof User ? $user->id : $request->ip()));
        });

        District::saved(ShowRealEstateOptions::forget(...));
        District::deleted(ShowRealEstateOptions::forget(...));
    }
}
