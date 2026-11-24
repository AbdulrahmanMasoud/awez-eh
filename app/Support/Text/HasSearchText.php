<?php

declare(strict_types=1);

namespace App\Support\Text;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Keeps a `search_text` column with the model's searchable attributes, normalized
 * (invariant 6). Search matches against this column, never the raw text.
 *
 * @phpstan-require-extends Model
 */
trait HasSearchText
{
    /**
     * The attributes whose text residents search, in the order they are joined.
     *
     * @return list<string>
     */
    abstract protected function searchableAttributes(): array;

    protected static function bootHasSearchText(): void
    {
        static::saving(static function (self $model): void {
            $model->refreshSearchText();
        });
    }

    private function refreshSearchText(): void
    {
        $attributes = $this->searchableAttributes();

        if ($this->exists && ! $this->isDirty($attributes)) {
            return;
        }

        $texts = array_map(function (string $attribute): string {
            $value = $this->getAttribute($attribute);

            return match (true) {
                $value === null => '',
                is_string($value) => $value,
                default => throw new LogicException("[{$attribute}] is searchable, so it must be text."),
            };
        }, $attributes);

        $this->setAttribute('search_text', new ArabicNormalizer()->normalize(implode(' ', $texts)));
    }
}
