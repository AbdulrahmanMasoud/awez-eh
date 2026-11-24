<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Route::get('/api/request-id', fn () => response()->json(['request_id' => Context::get('request_id')]));
});

it('gives every request a new request id when none is sent', function (): void {
    $response = $this->getJson('/api/request-id');

    $requestId = $response->headers->get('X-Request-Id');

    expect(Str::isUuid($requestId))->toBeTrue()
        ->and($response->json('request_id'))->toBe($requestId)
        ->and($this->getJson('/api/request-id')->headers->get('X-Request-Id'))->not->toBe($requestId);
});

it('keeps a safe incoming request id', function (string $incoming): void {
    $response = $this->getJson('/api/request-id', ['X-Request-Id' => $incoming]);

    $response->assertHeader('X-Request-Id', $incoming)->assertJsonPath('request_id', $incoming);
})->with([
    'a UUID' => '0196b1d2-5c4e-7a3b-9f1d-2e3c4b5a6d7e',
    'a short token' => 'cf-8f1c2d3e4f5a6b7c',
    'with dots and underscores' => 'edge_01.request',
]);

it('replaces an unsafe incoming request id', function (string $incoming): void {
    $response = $this->getJson('/api/request-id', ['X-Request-Id' => $incoming]);

    $requestId = $response->headers->get('X-Request-Id');

    expect($requestId)->not->toBe($incoming)
        ->and(Str::isUuid($requestId))->toBeTrue();
})->with([
    'too long' => str_repeat('a', 65),
    'spaces' => 'two words',
    'a quote' => 'id"><script>',
    'Arabic letters' => 'طلب-١',
    'empty' => '',
]);

it('gives requests outside the API a request id too', function (): void {
    expect(Str::isUuid($this->get('/up')->headers->get('X-Request-Id')))->toBeTrue();
});
