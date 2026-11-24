<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Redis;

it('reaches Redis', function (): void {
    expect(Redis::connection()->ping())->toBeTrue();
});
