<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Http\SignedInRequest;
use App\Http\ValidatesImageUpload;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateAvatarRequest extends FormRequest
{
    use SignedInRequest;
    use ValidatesImageUpload;

    public function authorize(): bool
    {
        return true;
    }
}
