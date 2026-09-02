<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // No seed data needed — short codes are meant to be randomly
        // generated per-request via UrlShortenerService, not pre-seeded.
    }
}
