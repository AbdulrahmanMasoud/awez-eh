<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Domains\RealEstate\Models\Property;
use App\Filament\Resources\Users\UserResource;
use App\Http\Middleware\Admin\SetAdminLocale;
use App\Support\AdminNumbers;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Number;

/** Arabic-Indic and Eastern Arabic-Indic digits. */
const NON_WESTERN_DIGITS = '/[\x{0660}-\x{0669}\x{06F0}-\x{06F9}]/u';

it('serves Readex Pro from the theme build, never from a font CDN', function (): void {
    $page = $this->get('/admin/login')->assertOk();

    expect($page->getContent())
        ->not->toContain('fonts.bunny.net')
        ->not->toContain('fonts.googleapis.com')
        ->toContain('font-family: "Readex Pro"')
        ->toMatch('#/build/assets/readex-pro-\d{3}-normal-[\w-]+\.woff2#');
    // Reads the theme build (`sail npm run build`; CI builds it before the tests).
});

it('writes Western digits in Arabic pages: numbers, dates and the dashboard', function (string $page): void {
    $this->withoutVite();
    $admin = User::factory()->admin()->create();
    Property::factory()->count(3)->create();
    $this->actingAs($admin);

    $html = (string) $this->get($page === 'user' ? UserResource::getUrl('view', ['record' => $admin]) : $page)->assertOk()->getContent();

    expect(preg_match(NON_WESTERN_DIGITS, $html))->toBe(0);
})->with(['/admin', '/admin/users', 'user', '/admin/activity-log', '/admin/cities']);

it('formats numbers and money in Western digits in the panel, Arabic text kept', function (): void {
    $this->withoutVite();
    Route::middleware(['web', SetAdminLocale::class])
        ->get('/__numbers', fn (): array => [Number::format(1234567.5), Number::currency(42500, 'EGP')]);

    [$number, $money] = $this->getJson('/__numbers')->assertOk()->json();

    expect($number)->toBe('1,234,567.5')
        ->and($money)->toContain('42,500.00')->toContain('ج.م.')
        ->and(preg_match(NON_WESTERN_DIGITS, $money))->toBe(0);
});

it('keeps Arabic text with Latin digits, and English as it is', function (): void {
    expect(AdminNumbers::localeFor('ar'))->toBe('ar-u-nu-latn')
        ->and(AdminNumbers::localeFor('en'))->toBe('en');
});
