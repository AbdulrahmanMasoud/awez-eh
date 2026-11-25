<?php

declare(strict_types=1);

use App\Domains\Identity\Support\PhoneCodeHasher;

it('hashes a code with the key, never storing the code itself', function (): void {
    $hasher = new PhoneCodeHasher('base64:a-key');
    $hash = $hasher->hash('123456');

    expect($hash)->toBe(hash_hmac('sha256', '123456', 'base64:a-key'))
        ->and($hash)->not->toBe(hash('sha256', '123456'))
        ->and(new PhoneCodeHasher('base64:another-key')->hash('123456'))->not->toBe($hash);
});

it('matches the right code only', function (): void {
    $hasher = new PhoneCodeHasher('base64:a-key');
    $hash = $hasher->hash('004215');

    expect($hasher->matches('004215', $hash))->toBeTrue()
        ->and($hasher->matches('4215', $hash))->toBeFalse()
        ->and($hasher->matches('004216', $hash))->toBeFalse();
});
