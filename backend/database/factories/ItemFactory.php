<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ItemSlot;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Item>
 */
final class ItemFactory extends Factory
{
    private const int MAX_CODE_LENGTH = 48;

    private const int MAX_NAME_LENGTH = 48;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::limit(fake()->unique()->slug(2), self::MAX_CODE_LENGTH, ''),
            'name' => Str::limit(fake()->words(2, true), self::MAX_NAME_LENGTH, ''),
            'slot' => fake()->randomElement(ItemSlot::cases()),
            'sprite_col' => 3,
            'sprite_row' => 0,
            'required_level' => 1,
            'price' => 0,
            'is_starter' => false,
        ];
    }

    public function forSlot(ItemSlot $slot): static
    {
        return $this->state(fn (array $attributes) => ['slot' => $slot]);
    }

    public function starter(): static
    {
        return $this->state(fn (array $attributes) => ['is_starter' => true]);
    }
}
