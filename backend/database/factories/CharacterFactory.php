<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Beard;
use App\Enums\HairColor;
use App\Enums\HairStyle;
use App\Enums\SkinTone;
use App\Models\Character;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Character>
 */
final class CharacterFactory extends Factory
{
    private const int MAX_NAME_LENGTH = 24;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => Str::limit(fake()->firstName(), self::MAX_NAME_LENGTH, ''),
            'skin_tone' => fake()->randomElement(SkinTone::cases()),
            'hair_style' => fake()->randomElement(HairStyle::cases()),
            'hair_color' => fake()->randomElement(HairColor::cases()),
            'beard' => fake()->randomElement(Beard::cases()),
        ];
    }

    public function withXp(int $xp, int $gold = 0): static
    {
        return $this->state(fn (array $attributes) => ['xp' => $xp, 'gold' => $gold]);
    }
}
