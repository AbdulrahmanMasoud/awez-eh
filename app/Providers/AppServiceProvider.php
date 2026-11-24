<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Identity\Models\User;
use App\Domains\Marketplace\Models\UsedItem;
use App\Domains\Places\Models\Place;
use App\Domains\RealEstate\Models\Property;
use App\Domains\Requests\Models\RequestReply;
use App\Domains\Requests\Models\ServiceRequest;
use App\Domains\Services\Models\Provider;
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

final class AppServiceProvider extends ServiceProvider
{
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

        // `composer dev` runs the queue worker and the logs: Sail already serves the app on
        // APP_PORT, and there is no frontend to build.
        DevCommands::only('queue', 'logs');
    }

    /**
     * Polymorphic columns (Sanctum's tokenable, reports' reportable) store these short names, never
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
    }
}
