<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelServiceProvider;
use App\Providers\IdentityServiceProvider;
use App\Providers\RealEstateServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelServiceProvider::class,
    IdentityServiceProvider::class,
    RealEstateServiceProvider::class,
];
