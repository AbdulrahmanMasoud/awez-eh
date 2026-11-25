<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use Illuminate\Foundation\Http\FormRequest;

final class SignInWithGoogleRequest extends FormRequest
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
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function bodyParameters(): array
    {
        return [
            'id_token' => ['description' => 'The ID token Google Sign-In gave the app.', 'example' => 'eyJhbGciOiJSUzI1NiIsImtpZCI6Ij...'],
            'device_name' => ['description' => 'Names the device\'s token: signing in again under the same name replaces it.', 'example' => 'Pixel 8 بتاع أحمد'],
        ];
    }
}
