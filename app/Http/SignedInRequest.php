<?php

declare(strict_types=1);

namespace App\Http;

use App\Domains\Identity\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * For Form Requests on routes behind auth:sanctum: the account and the access token the request
 * came with, typed.
 *
 * @phpstan-require-extends FormRequest
 */
trait SignedInRequest
{
    public function account(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    public function accessToken(): PersonalAccessToken
    {
        return $this->account()->currentAccessToken();
    }
}
