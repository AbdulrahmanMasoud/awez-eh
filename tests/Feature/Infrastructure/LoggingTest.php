<?php

declare(strict_types=1);

use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->logFile = storage_path('logs/testing-'.bin2hex(random_bytes(6)).'.json');
    config(['logging.channels.json.path' => $this->logFile]);
    Log::forgetChannel('json');
});

afterEach(function (): void {
    if (is_file($this->logFile)) {
        unlink($this->logFile);
    }
});

/**
 * The JSON log's entries, one decoded line each.
 *
 * @return list<array<string, mixed>>
 */
function jsonLogEntries(string $file): array
{
    $lines = array_values(array_filter(explode("\n", (string) file_get_contents($file))));

    return array_map(fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR), $lines);
}

it('writes one JSON line per entry, with the request id', function (): void {
    Route::get('/api/logs', function () {
        Log::channel('json')->info('Listing viewed', ['listing' => 7]);
        Log::channel('json')->warning('Second entry');

        return response()->noContent();
    });

    $requestId = $this->get('/api/logs')->headers->get('X-Request-Id');
    $entries = jsonLogEntries($this->logFile);

    expect($entries)->toHaveCount(2)
        ->and($entries[0]['message'])->toBe('Listing viewed')
        ->and($entries[0]['context'])->toBe(['listing' => 7])
        ->and($entries[0]['level_name'])->toBe('INFO')
        ->and($entries[0]['extra']['request_id'])->toBe($requestId)
        ->and($entries[1]['extra']['request_id'])->toBe($requestId);
});

it('never writes a phone number, in an object or in plain text', function (): void {
    Log::channel('json')->info('Code sent to 01012345678 and +201112345678', [
        'phone' => PhoneNumber::from('01512345678'),
        'nested' => ['owner' => '00201212345678'],
    ]);

    $line = (string) file_get_contents($this->logFile);

    expect($line)->not->toContain('1012345678')->not->toContain('1112345678')
        ->not->toContain('1512345678')->not->toContain('1212345678')
        ->toContain('010*****678')->toContain('011*****678')->toContain('015*****678')->toContain('012*****678');
});

it('masks landlines written as text too', function (): void {
    Log::channel('json')->info('Place called on 0482345678, 0223456789 and +2035123456');

    $line = (string) file_get_contents($this->logFile);

    expect($line)->not->toContain('482345678')->not->toContain('223456789')->not->toContain('35123456')
        ->toContain('048****678')->toContain('022****789')->toContain('035***456');
});

it('never writes tokens, passwords or codes', function (): void {
    Log::channel('json')->info('Signed in', [
        'token' => '1|plain-text-token',
        'access_token' => 'ya29.secret',
        'password' => 'hunter2',
        'code' => '483920',
        'verification_code' => '112233',
        'headers' => ['Authorization' => 'Bearer 2|another-token'],
        'error_code' => 'invalid_phone_number',
    ]);

    $entry = jsonLogEntries($this->logFile)[0];

    expect(json_encode($entry))->not->toContain('plain-text-token')->not->toContain('ya29.secret')
        ->not->toContain('hunter2')->not->toContain('483920')->not->toContain('112233')->not->toContain('another-token')
        ->and($entry['context']['token'])->toBe('[redacted]')
        ->and($entry['context']['headers']['Authorization'])->toBe('[redacted]')
        ->and($entry['context']['error_code'])->toBe('invalid_phone_number');
});

it('redacts the default log channel too', function (): void {
    $file = storage_path('logs/testing-'.bin2hex(random_bytes(6)).'.log');
    config(['logging.channels.single.path' => $file]);
    Log::forgetChannel('single');

    Log::channel('single')->info('Calling 01012345678', ['token' => 'abc']);

    $line = (string) file_get_contents($file);
    unlink($file);

    expect($line)->not->toContain('1012345678')->not->toContain('abc');
});
