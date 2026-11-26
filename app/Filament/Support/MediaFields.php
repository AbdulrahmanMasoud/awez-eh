<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Domains\Media\Actions\AddImage;
use App\Domains\Media\Contracts\HasMediaCollections;
use App\Domains\Media\Settings\MediaSettings;
use App\Domains\Media\Support\MediaDisks;
use App\Domains\Media\Support\MediaTypes;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * The panel's pieces for a collection of photos, for every resource that shows or edits them:
 * thumbnails, drag to reorder, remove. An admin's upload is cleaned as a resident's is (AddImage):
 * the plugin's own upload would keep the file's metadata.
 */
final class MediaFields
{
    private const string THUMB = 'thumb';

    private const int COLUMN_LIMIT = 3;

    public static function images(string $collection): SpatieMediaLibraryFileUpload
    {
        return SpatieMediaLibraryFileUpload::make($collection)
            ->collection($collection)
            // Previews come from the public small size, never from the private original.
            ->disk(MediaDisks::public())
            ->visibility('public')
            ->conversion(self::THUMB)
            ->image()
            ->acceptedFileTypes(MediaTypes::IMAGES)
            ->maxSize(fn (): int => resolve(MediaSettings::class)->maxFileSizeKilobytes())
            ->maxFiles(fn (?Model $record): ?int => $record instanceof HasMediaCollections ? $record->mediaLimit($collection) : null)
            ->multiple(fn (?Model $record): bool => ! $record instanceof HasMediaCollections || $record->mediaLimit($collection) > 1)
            ->reorderable()
            ->panelLayout('grid')
            ->saveUploadedFileUsing(static function (TemporaryUploadedFile $file, ?Model $record) use ($collection): ?string {
                if (! $record instanceof HasMediaCollections) {
                    return null;
                }

                return resolve(AddImage::class)->handle($record, $collection, $file->getRealPath())->uuid;
            });
    }

    public static function column(string $collection): SpatieMediaLibraryImageColumn
    {
        return SpatieMediaLibraryImageColumn::make($collection)
            ->collection($collection)
            ->conversion(self::THUMB)
            ->circular()
            ->stacked()
            ->limit(self::COLUMN_LIMIT);
    }

    public static function entry(string $collection): SpatieMediaLibraryImageEntry
    {
        return SpatieMediaLibraryImageEntry::make($collection)
            ->collection($collection)
            ->conversion(self::THUMB);
    }
}
