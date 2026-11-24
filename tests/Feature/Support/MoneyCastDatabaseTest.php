<?php

declare(strict_types=1);

use App\Support\Casts\MoneyCast;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

#[Unguarded]
#[Table(name: 'money_cast_probes')]
#[WithoutTimestamps]
final class MoneyCastProbe extends Model
{
    protected function casts(): array
    {
        return ['price' => MoneyCast::class];
    }
}

it('round-trips money through a bigint column in PostgreSQL', function (): void {
    // Created inside the test's transaction, so it disappears with it: no migration needed.
    Schema::create('money_cast_probes', function (Blueprint $table): void {
        $table->id();
        $table->bigInteger('price')->nullable();
        $table->string('currency', 3)->nullable();
    });

    $probe = MoneyCastProbe::query()->create(['price' => Money::ofMinor('9007199254740993', 'EGP')]);

    $stored = MoneyCastProbe::query()->findOrFail($probe->id);

    expect($stored->price->getMinorAmount()->toInt())->toBe(9_007_199_254_740_993)
        ->and($stored->price->getCurrency()->getCurrencyCode())->toBe('EGP')
        ->and(Schema::getColumnType('money_cast_probes', 'price'))->toBe('int8');
});
