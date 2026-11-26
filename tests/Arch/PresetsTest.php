<?php

declare(strict_types=1);

arch()->preset()->php();

arch()->preset()->security();

// The preset expects models, enums, exceptions, mail and notifications in App\Models, App\Enums, ...;
// CLAUDE.md keeps each domain's own inside the domain. ConventionsTest checks those layers instead.
// The admin panel's own enums (navigation groups, the dashboard's periods) are panel UI, not
// domain types, and stay with the panel.
arch()->preset()->laravel()->ignoring([
    ...array_merge(...array_map(domainLayerNamespaces(...), ['Models', 'Enums', 'Exceptions', 'Mail', 'Notifications'])),
    'App\Filament\Navigation',
    'App\Filament\Dashboard',
]);
