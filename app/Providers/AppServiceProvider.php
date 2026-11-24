<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\DevCommands;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // `composer dev` runs the queue worker and the logs: Sail already serves the app on
        // APP_PORT, and there is no frontend to build.
        DevCommands::only('queue', 'logs');
    }
}
