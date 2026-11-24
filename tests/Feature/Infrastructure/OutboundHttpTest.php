<?php

declare(strict_types=1);

use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;

it('refuses an outbound HTTP call that is not faked', function (): void {
    expect(fn () => Http::get('https://example.com'))->toThrow(StrayRequestException::class);
});

it('allows an outbound HTTP call that is faked', function (): void {
    Http::fake(['example.com/*' => Http::response(['ok' => true])]);

    expect(Http::get('https://example.com/status')->json('ok'))->toBeTrue();
});
