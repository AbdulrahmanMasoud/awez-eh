<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use Illuminate\Foundation\Http\FormRequest;

final class SignInWithAppleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string', 'max:8192'],
            'nonce' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function bodyParameters(): array
    {
        return [
            'id_token' => ['description' => 'The identity token Sign in with Apple gave the app.', 'example' => 'eyJraWQiOiJXNldjT0tCIiwiYWxnIjoiUlMyNTYifQ...'],
            'nonce' => ['description' => 'The raw nonce the app generated; Apple put its SHA-256 in the token.', 'example' => '4f8a0c1e9b2d4c7a8e6f'],
            'name' => ['description' => 'The name Apple gave the app. Apple sends it on the first sign-in only.', 'example' => 'منى عبد الرحمن'],
            'device_name' => ['description' => 'Names the device\'s token: signing in again under the same name replaces it.', 'example' => 'iPhone منى'],
        ];
    }
}
