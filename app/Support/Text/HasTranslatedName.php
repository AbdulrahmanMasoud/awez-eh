<?php

declare(strict_types=1);

namespace App\Support\Text;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use LogicException;

/**
 * Reference data an admin edits in both languages (`name_ar`, `name_en`): `name` is the one in
 * the app's locale, Arabic for any locale the app does not speak.
 *
 * @phpstan-require-extends Model
 */
trait HasTranslatedName
{
    /**
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(function (): string {
            $name = $this->getAttribute(App::isLocale('en') ? 'name_en' : 'name_ar');

            if (! is_string($name)) {
                throw new LogicException('A translated name is text.');
            }

            return $name;
        });
    }
}
