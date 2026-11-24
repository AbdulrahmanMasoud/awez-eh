<?php

declare(strict_types=1);

use App\Support\Text\HasTranslatedName;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

function translatedNameModel(): Model
{
    $model = new #[Unguarded] class extends Model
    {
        use HasTranslatedName;
    };

    return $model->fill(['name_ar' => 'مدينة السادات', 'name_en' => 'Sadat City']);
}

it('gives the name in the app locale', function (string $locale, string $name): void {
    App::setLocale($locale);

    expect(translatedNameModel()->getAttribute('name'))->toBe($name);
})->with([
    'Arabic' => ['ar', 'مدينة السادات'],
    'English' => ['en', 'Sadat City'],
    'a locale the app does not speak falls back to Arabic' => ['fr', 'مدينة السادات'],
]);
