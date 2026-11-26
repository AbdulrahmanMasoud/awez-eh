<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Media;

use App\Http\SignedInRequest;
use App\Http\ValidatesImageUpload;
use Illuminate\Foundation\Http\FormRequest;

final class StoreUploadRequest extends FormRequest
{
    use SignedInRequest;
    use ValidatesImageUpload;

    public function authorize(): bool
    {
        return true;
    }
}
