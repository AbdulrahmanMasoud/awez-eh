<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\Geo\Point;
use App\Support\LucideIcons;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;

/**
 * The fields reference data shares (cities, districts, trades, place categories): a name in
 * each language, a slug, a Lucide icon, and coordinates typed as latitude and longitude.
 */
final class ReferenceDataFields
{
    private const int MAX_TEXT = 255;

    /**
     * @return list<TextInput>
     */
    public static function names(): array
    {
        return [
            TextInput::make('name_ar')->label(__('admin.fields.name_ar'))->required()->maxLength(self::MAX_TEXT),
            TextInput::make('name_en')->label(__('admin.fields.name_en'))->required()->maxLength(self::MAX_TEXT),
        ];
    }

    public static function slug(bool $unique = true): TextInput
    {
        $slug = TextInput::make('slug')
            ->label(__('admin.fields.slug'))
            ->required()
            ->maxLength(self::MAX_TEXT)
            ->alphaDash();

        return $unique ? $slug->unique(ignoreRecord: true) : $slug;
    }

    public static function active(): Toggle
    {
        return Toggle::make('is_active')->label(__('admin.fields.is_active'))->default(true);
    }

    public static function icon(): Select
    {
        $options = array_map(
            fn (string $name): string => '<span class="awez-icon-option">'.svg('lucide-'.$name, 'awez-icon-option__svg')->toHtml().'<span>'.e($name).'</span></span>',
            array_combine(LucideIcons::NAMES, LucideIcons::NAMES),
        );

        return Select::make('icon')
            ->label(__('admin.fields.icon'))
            ->options($options)
            ->allowHtml()
            ->searchable()
            ->required()
            ->in(LucideIcons::NAMES);
    }

    public static function iconColumn(): IconColumn
    {
        return IconColumn::make('icon')
            ->label(__('admin.fields.icon'))
            ->icon(fn (string $state): string => 'lucide-'.$state)
            ->tooltip(fn (string $state): string => $state)
            ->color('gray');
    }

    /**
     * Latitude and longitude, kept out of the model and turned into its point by
     * MapsCenterCoordinates. A map picker (MapLibre, ADR 0005) comes in a later step.
     */
    public static function center(bool $required, string $help): Grid
    {
        return Grid::make(2)->schema([
            TextInput::make('latitude')->label(__('admin.fields.latitude'))->numeric()->minValue(-90)->maxValue(90)->required($required)->helperText($help),
            TextInput::make('longitude')->label(__('admin.fields.longitude'))->numeric()->minValue(-180)->maxValue(180)->required($required),
        ]);
    }

    /**
     * @return list<TextColumn>
     */
    public static function nameColumns(): array
    {
        return [
            TextColumn::make('name_ar')->label(__('admin.fields.name_ar'))->searchable(),
            TextColumn::make('name_en')->label(__('admin.fields.name_en'))->searchable(),
        ];
    }

    public static function activeColumn(): IconColumn
    {
        return IconColumn::make('is_active')->label(__('admin.fields.is_active'))->boolean();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function coordinatesFromCenter(array $data, ?Point $center): array
    {
        // The point itself stays out of the form: Livewire carries plain values only.
        unset($data['center']);

        return [...$data, 'latitude' => $center?->latitude, 'longitude' => $center?->longitude];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function centerFromCoordinates(array $data): array
    {
        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;
        unset($data['latitude'], $data['longitude']);

        $data['center'] = is_numeric($latitude) && is_numeric($longitude) ? new Point((float) $latitude, (float) $longitude) : null;

        return $data;
    }
}
