<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Domains\Identity\Enums\DevicePlatform;
use App\Http\SignedInRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class SaveDeviceTokenRequest extends FormRequest
{
    use SignedInRequest;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
            'app_version' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function platform(): DevicePlatform
    {
        return $this->enum('platform', DevicePlatform::class) ?? DevicePlatform::Android;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function bodyParameters(): array
    {
        return [
            'token' => ['description' => 'The device\'s Firebase Cloud Messaging registration token.', 'example' => 'dGVzdC1mY20tdG9rZW4:APA91bH...'],
            'platform' => ['description' => '`ios` or `android`.', 'example' => 'android'],
            'app_version' => ['description' => 'The app\'s version.', 'example' => '1.0.0'],
        ];
    }
}
