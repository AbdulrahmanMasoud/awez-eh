<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Str;

it('gives users UUID v7 primary keys', function (): void {
    $user = User::factory()->create();

    expect(Str::isUuid($user->id, 7))->toBeTrue()
        ->and(User::query()->find($user->id)?->is($user))->toBeTrue();
});

it('issues Sanctum tokens to users with UUID keys', function (): void {
    $user = User::factory()->create();

    $user->createToken('Test phone');

    expect($user->tokens()->sole()->tokenable_id)->toBe($user->id);
});
