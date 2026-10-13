<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Character;
use App\Models\InventoryItem;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
final class InventoryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'item_id' => Item::factory(),
            'acquired_at' => now(),
        ];
    }
}
