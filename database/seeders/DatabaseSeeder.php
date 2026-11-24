<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

// Model events stay on: HasSearchText fills search_text on saving.
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);

        // Made-up residents and listings: never in production, nor anywhere real people look.
        if (App::environment(DemoContentSeeder::ENVIRONMENTS)) {
            $this->call([DemoContentSeeder::class, DemoInteractionSeeder::class]);
        }
    }
}
