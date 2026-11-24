<?php

declare(strict_types=1);

use App\Domains\Identity\Models\User;
use App\Exceptions\DomainException;
use App\Support\PhoneNumber;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * A domain error with its own status, as a domain's Exceptions/ will hold.
 */
final class ListingAlreadyRented extends DomainException
{
    public function __construct()
    {
        parent::__construct('listing_already_rented', 'errors.not_found', 409, ['listing_id' => 'secret-listing-id']);
    }
}

// Routes that exist only in these tests, one per kind of error.
beforeEach(function (): void {
    Route::middleware('api')->prefix('api/errors')->group(function (): void {
        Route::get('domain', fn (): PhoneNumber => PhoneNumber::from('not a number'));
        Route::get('domain-with-status', fn (): never => throw new ListingAlreadyRented);
        Route::post('validation', fn (Request $request) => $request->validate(['name' => ['required'], 'age' => ['integer']]));
        Route::get('unauthenticated', fn (): string => 'never')->middleware('auth:sanctum');
        Route::get('forbidden', fn (): never => throw new AuthorizationException('Only the owner of listing 42.'));
        Route::get('missing-model', fn () => User::query()->findOrFail('0196b1d2-5c4e-7a3b-9f1d-2e3c4b5a6d7e'));
        Route::get('server-error', fn (): never => throw new RuntimeException('The database password is hunter2.'));
        Route::get('only-get', fn (): string => 'ok');
    });
});

/**
 * The one error shape every API error uses.
 */
function expectApiError(TestResponse $response, int $status, string $code): TestResponse
{
    $response->assertStatus($status)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('code', $code);

    expect(array_keys($response->json()))->toBe(['message', 'code', 'errors'])
        ->and($response->json('message'))->toBeString()->not->toBeEmpty();

    return $response;
}

it('renders a domain error with its code, status and translated message', function (): void {
    $response = expectApiError($this->getJson('/api/errors/domain'), 422, 'invalid_phone_number');

    expect($response->json('message'))->toBe(__('errors.invalid_phone_number', [], 'ar'))
        ->and($response->getContent())->toContain('"errors":{}');
});

it('renders a domain error in English when asked', function (): void {
    $response = $this->getJson('/api/errors/domain', ['Accept-Language' => 'en']);

    expect($response->json('message'))->toBe(__('errors.invalid_phone_number', [], 'en'));
});

it('uses the status a domain error chooses and never shows its context', function (): void {
    $response = expectApiError($this->getJson('/api/errors/domain-with-status'), 409, 'listing_already_rented');

    expect($response->getContent())->not->toContain('secret-listing-id');
});

it('does not report domain errors to the error tracker', function (): void {
    Exceptions::fake();

    $this->getJson('/api/errors/domain-with-status');
    $this->getJson('/api/errors/server-error');

    Exceptions::assertNotReported(ListingAlreadyRented::class);
    Exceptions::assertReported(RuntimeException::class);
});

it('renders validation errors keyed by field', function (): void {
    $response = expectApiError($this->postJson('/api/errors/validation', ['age' => 'old']), 422, 'validation_failed');

    expect($response->json('errors'))->toHaveKeys(['name', 'age'])
        ->and($response->json('errors.name'))->toBeArray()->not->toBeEmpty()
        ->and($response->json('message'))->toBe(__('errors.validation_failed', [], 'ar'));
});

it('writes the validation message of each field in the language the client asks for', function (string $language, string $required, string $integer): void {
    $response = $this->postJson('/api/errors/validation', ['age' => 'old'], ['Accept-Language' => $language]);

    $response->assertUnprocessable()
        ->assertJsonPath('errors.name.0', $required)
        ->assertJsonPath('errors.age.0', $integer);
})->with([
    'Arabic' => ['ar', 'حقل name مطلوب.', 'يجب أن يكون حقل age عددًا صحيحًا.'],
    'English' => ['en', 'The name field is required.', 'The age field must be an integer.'],
]);

it('renders a missing sign-in as 401', function (): void {
    expectApiError($this->getJson('/api/errors/unauthenticated'), 401, 'unauthenticated');
});

it('renders a refused action as 403 without its internal message', function (): void {
    $response = expectApiError($this->getJson('/api/errors/forbidden'), 403, 'forbidden');

    expect($response->getContent())->not->toContain('listing 42');
});

it('renders a missing model as 404 without naming the model or its id', function (): void {
    $response = expectApiError($this->getJson('/api/errors/missing-model'), 404, 'not_found');

    expect($response->getContent())->not->toContain('User')->not->toContain('0196b1d2');
});

it('renders an unknown API route as 404', function (): void {
    expectApiError($this->get('/api/v1/nowhere'), 404, 'not_found');
});

it('renders a wrong HTTP method as 405 and says which methods are allowed', function (): void {
    expectApiError($this->postJson('/api/errors/only-get'), 405, 'method_not_allowed')
        ->assertHeader('Allow', 'GET, HEAD');
});

it('renders a crash as 500 without its message or trace', function (): void {
    $response = expectApiError($this->getJson('/api/errors/server-error'), 500, 'server_error');

    expect($response->getContent())->not->toContain('hunter2')->not->toContain('trace')->not->toContain('.php');
});

it('adds the exception and its trace to a crash in local only', function (): void {
    app()->detectEnvironment(fn (): string => 'local');

    $response = $this->getJson('/api/errors/server-error')->assertStatus(500);

    expect($response->json('debug.exception'))->toBe(RuntimeException::class)
        ->and($response->json('debug.message'))->toBe('The database password is hunter2.')
        ->and($response->json('debug.trace'))->toBeArray()->not->toBeEmpty();
});

it('answers in JSON even when the client asks for HTML', function (): void {
    expectApiError($this->get('/api/errors/domain', ['Accept' => 'text/html']), 422, 'invalid_phone_number');
});

it('leaves errors outside the API alone', function (): void {
    $response = $this->get('/'.Str::random(12));

    $response->assertNotFound();
    expect($response->headers->get('Content-Type'))->toStartWith('text/html');
});
