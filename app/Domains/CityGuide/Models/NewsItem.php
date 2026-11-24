<?php

declare(strict_types=1);

namespace App\Domains\CityGuide\Models;

use App\Domains\Geography\Models\City;
use App\Domains\Identity\Models\User;
use App\Support\Text\HasSearchText;
use Database\Factories\NewsItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * City news, written by admins.
 */
#[Fillable(['title', 'body', 'is_published', 'published_at'])]
#[UseFactory(NewsItemFactory::class)]
final class NewsItem extends Model
{
    /** @use HasFactory<NewsItemFactory> */
    use HasFactory;

    use HasSearchText;
    use HasUuids;

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * The admin who wrote it.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected function searchableAttributes(): array
    {
        return ['title', 'body'];
    }
}
