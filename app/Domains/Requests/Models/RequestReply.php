<?php

declare(strict_types=1);

namespace App\Domains\Requests\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Moderation\Models\Concerns\Reportable;
use App\Domains\Requests\Policies\RequestReplyPolicy;
use App\Support\Casts\MoneyCast;
use Database\Factories\RequestReplyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An answer to a request, seen only by its author and the request's owner.
 */
#[Fillable(['body', 'offered_price'])]
#[UseFactory(RequestReplyFactory::class)]
#[UsePolicy(RequestReplyPolicy::class)]
final class RequestReply extends Model
{
    /** @use HasFactory<RequestReplyFactory> */
    use HasFactory;

    use HasUuids;
    use Reportable;

    /**
     * @return BelongsTo<ServiceRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }

    /**
     * The advertiser or craftsman who replied.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'offered_price' => MoneyCast::class,
        ];
    }
}
