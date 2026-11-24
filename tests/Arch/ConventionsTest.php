<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

arch('every class declares strict types')
    ->expect(['App', 'Database', 'Tests'])
    ->toUseStrictTypes();

arch('debug helpers are never committed')
    ->expect(['dd', 'ddd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

// Classes are analysed by namespace and config/ files have none, so this leaves env()
// allowed in config/ only.
arch('env() is read only in config files')
    ->expect('env')
    ->not->toBeUsed();

arch('actions are final and expose handle()')
    ->expect(domainLayerNamespaces('Actions'))
    ->classes()
    ->toBeFinal()
    ->toHaveMethod('handle');

arch('domains do not depend on the HTTP layer or the admin panel')
    ->expect('App\Domains')
    ->not->toUse(['App\Http', 'App\Filament']);

arch('controllers do not query through the DB facade')
    ->expect('App\Http\Controllers')
    ->not->toUse(DB::class);

arch('jobs are queued')
    ->expect(['App\Jobs', ...domainLayerNamespaces('Jobs')])
    ->classes()
    ->toImplement(ShouldQueue::class);

arch('form requests are final form requests')
    ->expect('App\Http\Requests')
    ->classes()
    ->toBeFinal()
    ->toExtend(FormRequest::class);

arch('support code depends on no domain, HTTP or admin code')
    ->expect('App\Support')
    ->not->toUse(['App\Domains', 'App\Http', 'App\Filament']);
