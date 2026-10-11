<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Availability;
use App\Enums\PaintingType;
use App\Models\Category;
use App\Models\User;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Painting> */
final class PaintingFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'artist_id' => User::factory()->artist(),
            'category_id' => Category::factory(),
            'title' => $title,
            'slug' => Slug::make($title) . '-' . fake()->unique()->randomNumber(5),
            'sku' => 'WD-' . fake()->unique()->randomNumber(6),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'price' => fake()->randomElement([1200, 2400, 3600, 5000]),
            'painting_type' => PaintingType::ReadyMade,
            'availability' => Availability::Available,
            'stock_quantity' => 1,
            'width' => 60,
            'height' => 90,
            'unit' => 'cm',
            'deposit_percentage' => 50,
            'published_at' => now()->subDay(),
        ];
    }

    /** لوحة غير منشورة — مسوّدة لا تظهر في المعرض. */
    public function draft(): static
    {
        return $this->state(fn () => ['published_at' => null]);
    }

    public function sold(): static
    {
        return $this->state(fn () => [
            'availability' => Availability::Sold,
            'stock_quantity' => 0,
        ]);
    }

    public function reservable(): static
    {
        return $this->state(fn () => [
            'painting_type' => PaintingType::Reservable,
            'production_days' => 21,
        ]);
    }
}
