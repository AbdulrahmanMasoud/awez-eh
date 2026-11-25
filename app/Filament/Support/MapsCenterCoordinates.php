<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\Geo\Point;

/**
 * For create and edit pages of records with a `center` point: the form edits it as latitude and
 * longitude.
 */
trait MapsCenterCoordinates
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $center = $data['center'] ?? null;

        return ReferenceDataFields::coordinatesFromCenter($data, $center instanceof Point ? $center : null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ReferenceDataFields::centerFromCoordinates($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ReferenceDataFields::centerFromCoordinates($data);
    }
}
