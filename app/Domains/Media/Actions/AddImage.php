<?php

declare(strict_types=1);

namespace App\Domains\Media\Actions;

use App\Domains\Media\Images\ImageCleaner;
use App\Domains\Media\Models\Media;
use LogicException;
use Spatie\MediaLibrary\HasMedia;

/**
 * Stores an uploaded image in a model's collection: cleaned first (ImageCleaner), so neither the
 * uploaded bytes nor the client's file name are ever kept. The sizes follow on the `media` queue.
 */
final readonly class AddImage
{
    private const string FILE_NAME = 'photo.jpg';

    public function __construct(private ImageCleaner $cleaner) {}

    public function handle(HasMedia $model, string $collection, string $path): Media
    {
        $image = $this->cleaner->clean($path);

        $media = $model->addMedia($image->path)
            ->usingName(pathinfo(self::FILE_NAME, PATHINFO_FILENAME))
            ->usingFileName(self::FILE_NAME)
            ->withProperties(['width' => $image->width, 'height' => $image->height])
            ->toMediaCollection($collection);

        if (! $media instanceof Media) {
            throw new LogicException('media-library.media_model must be '.Media::class.'.');
        }

        return $media;
    }
}
