<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Widgets\Widget;

arch('Filament resources, pages and widgets live in App\Filament')
    ->expect([Resource::class, Page::class, Widget::class])
    ->toOnlyBeUsedIn(['App\Filament', 'App\Providers\Filament', 'Tests']);

arch('Filament classes are final')
    ->expect('App\Filament')
    ->classes()
    ->toBeFinal();

arch('admin permissions are checked through the User model, inside the panel only')
    ->expect('App\Domains')
    ->not->toUse([Filament::class]);
