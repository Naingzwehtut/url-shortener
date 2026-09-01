<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Url;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Url>
 */
class UrlFactory extends Factory
{
    protected $model = Url::class;

    public function definition(): array
    {
        return [
            'url' => $this->faker->url(),
            'short_code' => Str::random(6),
            'access_count' => 0,
        ];
    }
}
