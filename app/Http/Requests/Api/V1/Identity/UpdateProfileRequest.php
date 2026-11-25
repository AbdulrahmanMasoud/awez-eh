<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Domains\Identity\Data\ProfileChanges;
use App\Domains\Identity\Enums\UserLocale;
use App\Http\SignedInRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;

final class UpdateProfileRequest extends FormRequest
{
    use SignedInRequest;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|Enum|Exists>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'locale' => ['sometimes', 'required', Rule::enum(UserLocale::class)],
            'district_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('districts', 'id')],
        ];
    }

    public function toData(): ProfileChanges
    {
        return new ProfileChanges(
            name: $this->has('name') ? $this->string('name')->trim()->toString() : null,
            locale: $this->has('locale') ? $this->enum('locale', UserLocale::class) : null,
            changesDistrict: $this->has('district_id'),
            districtId: $this->filled('district_id') ? $this->string('district_id')->toString() : null,
        );
    }

    /**
     * @return array<string, array<string, string|null>>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'The name other residents see.', 'example' => 'أحمد السيد'],
            'locale' => ['description' => 'The language of notifications: `ar` or `en`.', 'example' => 'ar'],
            'district_id' => ['description' => 'Where the resident lives, one of the city\'s districts; null clears it.', 'example' => null],
        ];
    }
}
