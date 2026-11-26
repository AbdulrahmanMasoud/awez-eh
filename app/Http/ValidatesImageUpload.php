<?php

declare(strict_types=1);

namespace App\Http;

use App\Domains\Media\Settings\MediaSettings;
use App\Domains\Media\Support\MediaTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

/**
 * One image in the multipart field `file`, checked by its content (finfo), never by its name or
 * the type the client claims, and within MediaSettings' size.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesImageUpload
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimetypes:'.implode(',', MediaTypes::IMAGES), 'max:'.resolve(MediaSettings::class)->maxFileSizeKilobytes()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => (string) __('media.validation.required'),
            'file.file' => (string) __('media.validation.required'),
            'file.mimetypes' => (string) __('media.validation.format'),
            'file.max' => (string) __('media.validation.size', ['max' => resolve(MediaSettings::class)->max_file_size_mb]),
        ];
    }

    public function uploadedImage(): UploadedFile
    {
        $file = $this->file('file');

        // rules() asked for exactly one file.
        if (! $file instanceof UploadedFile) {
            throw new LogicException('The request was not validated.');
        }

        return $file;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function bodyParameters(): array
    {
        return [
            'file' => ['description' => 'A JPEG, PNG or WebP photo, up to 10 MB by default. It is stored without its metadata (location, camera), upright and at most 2560 px on its long side.'],
        ];
    }
}
