<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Http\SignedInRequest;
use Illuminate\Foundation\Http\FormRequest;

final class ConfirmPhoneRequest extends FormRequest
{
    use SignedInRequest;

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
            'code' => ['required', 'string', 'digits:6'],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function bodyParameters(): array
    {
        return [
            'code' => ['description' => 'The six-digit code.', 'example' => '483920'],
        ];
    }
}
