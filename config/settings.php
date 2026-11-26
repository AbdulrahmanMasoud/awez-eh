<?php

declare(strict_types=1);

use App\Domains\Media\Settings\MediaSettings;
use Spatie\LaravelData\Data;
use Spatie\LaravelSettings\SettingsCasts\DataCast;
use Spatie\LaravelSettings\SettingsCasts\DateTimeInterfaceCast;
use Spatie\LaravelSettings\SettingsCasts\DateTimeZoneCast;
use Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository;

/*
| spatie/laravel-settings: values an admin tunes in the panel. Each class lives in its domain's
| Settings/ folder and is listed here (no discovery); its defaults are a migration in
| database/settings, run by `migrate` with the others.
*/
return [

    'settings' => [
        MediaSettings::class,
    ],

    'setting_class_path' => app_path('Domains'),

    'migrations_paths' => [
        database_path('settings'),
    ],

    'default_repository' => 'database',

    'repositories' => [
        'database' => [
            'type' => DatabaseSettingsRepository::class,
            'model' => null,
            'table' => null,
            'connection' => null,
        ],
    ],

    'encoder' => null,

    'decoder' => null,

    // Read once per request from the table; a change in the panel applies to the next request.
    'cache' => [
        'enabled' => false,
        'store' => null,
        'prefix' => null,
        'ttl' => null,
        'memo' => false,
    ],

    'global_casts' => [
        DateTimeInterface::class => DateTimeInterfaceCast::class,
        DateTimeZone::class => DateTimeZoneCast::class,
        Data::class => DataCast::class,
    ],

    'auto_discover_settings' => [],

    'discovered_settings_cache_path' => base_path('bootstrap/cache'),

];
