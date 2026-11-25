<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Http\SignedInRequest;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

final class StartPhoneVerificationRequest extends FormRequest
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
            'phone' => ['required', 'string', 'max:32'],
        ];
    }

    /**
     * An Egyptian mobile, typed any common way; anything else is `invalid_phone_number`.
     */
    public function phone(): PhoneNumber
    {
        return PhoneNumber::from($this->string('phone')->toString());
    }

    public function ipAddress(): string
    {
        return (string) $this->ip();
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function bodyParameters(): array
    {
        return [
            'phone' => ['description' => 'An Egyptian mobile number (010, 011, 012, 015), with or without +20; Arabic-Indic digits work too.', 'example' => '01012345678'],
        ];
    }
}
