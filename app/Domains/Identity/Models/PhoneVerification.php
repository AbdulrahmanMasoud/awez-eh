<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Identity\Enums\PhoneVerificationDriver;
use App\Domains\Identity\Policies\PhoneVerificationPolicy;
use App\Support\Casts\PhoneNumberCast;
use Carbon\CarbonImmutable;
use Database\Factories\PhoneVerificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Date;

/**
 * A number being confirmed. It holds an HMAC of the code, never the code. Rows are kept after
 * they expire, because the rate limits count them.
 */
#[Fillable(['phone', 'driver', 'code_hash', 'expires_at', 'ip_address'])]
#[Hidden(['code_hash'])]
#[UseFactory(PhoneVerificationFactory::class)]
#[UsePolicy(PhoneVerificationPolicy::class)]
final class PhoneVerification extends Model
{
    /** @use HasFactory<PhoneVerificationFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Neither confirmed nor expired. Used-up attempts are the confirming Action's check.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function open(Builder $query, ?CarbonImmutable $moment = null): void
    {
        $query->whereNull('confirmed_at')->where('expires_at', '>', $moment ?? Date::now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone' => PhoneNumberCast::class,
            'driver' => PhoneVerificationDriver::class,
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }
}
