<?php

declare(strict_types=1);

use App\Domains\Places\Models\Place;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

it('stores a place\'s landline in E.164 and reads it back', function (): void {
    $place = Place::factory()->create(['phone' => PhoneNumber::fromMobileOrLandline('048 234 5678')]);

    expect(DB::table('places')->where('id', $place->id)->value('phone'))->toBe('+20482345678')
        ->and(Place::query()->findOrFail($place->id)->phone?->national())->toBe('0482345678');
});

it('keeps a place\'s WhatsApp number a mobile', function (): void {
    expect(fn (): Place => Place::factory()->create(['whatsapp' => PhoneNumber::fromMobileOrLandline('0482345678')]))
        ->toThrow(InvalidArgumentException::class, '[whatsapp] takes a mobile number');
});
