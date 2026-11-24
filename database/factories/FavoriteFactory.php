<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Favorites\Models\Favorite;
use App\Domains\Identity\Models\User;
use App\Domains\RealEstate\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Favorite>
 */
final class FavoriteFactory extends Factory
{
    #[\Override]
    protected $model = Favorite::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'favoritable_type' => new Property()->getMorphClass(),
            'favoritable_id' => Property::factory()->published(),
        ];
    }
}
