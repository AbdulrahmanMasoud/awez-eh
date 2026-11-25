<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use Laravel\Sanctum\Sanctum;

it('answers in the language the client prefers, Arabic otherwise', function (?string $acceptLanguage, string $locale): void {
    $headers = $acceptLanguage === null ? [] : ['Accept-Language' => $acceptLanguage];

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/me', $headers)
        ->assertOk()
        ->assertHeader('Content-Language', $locale);
})->with([
    'no header' => [null, 'ar'],
    'Arabic' => ['ar', 'ar'],
    'Egyptian Arabic' => ['ar-EG', 'ar'],
    'English' => ['en', 'en'],
    'American English' => ['en-US,en;q=0.9', 'en'],
    'an unsupported language' => ['fr', 'ar'],
    'an unsupported language first' => ['fr-FR, en;q=0.8', 'en'],
    'weights that prefer English' => ['ar;q=0.4, en;q=0.9', 'en'],
    'anything' => ['*', 'ar'],
    'nonsense' => ['%%%', 'ar'],
]);

it('sets the language on API errors too', function (): void {
    $this->get('/api/v1/nowhere', ['Accept-Language' => 'en'])
        ->assertNotFound()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('message', __('errors.not_found', [], 'en'));
});

it('makes Arabic the default locale', function (): void {
    expect(config('app.locale'))->toBe('ar')
        ->and(config('app.fallback_locale'))->toBe('en');
});

it('answers a signed-in request in the language the client prefers, whatever the account\'s', function (): void {
    Sanctum::actingAs(User::factory()->create(['locale' => 'ar']));

    $this->patchJson('/api/v1/me', ['locale' => 'fr'], ['Accept-Language' => 'en'])
        ->assertUnprocessable()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('message', __('errors.validation_failed', [], 'en'));
});
