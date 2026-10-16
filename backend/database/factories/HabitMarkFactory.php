<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\HabitDirection;
use App\Models\HabitMark;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HabitMark>
 */
final class HabitMarkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory()->habit(),
            'period_date' => now()->toDateString(),
            'direction' => HabitDirection::Up,
            'xp_awarded' => 0,
            'gold_awarded' => 0,
            'created_at' => now(),
        ];
    }

    // The user's local calendar day (Y-m-d) the mark counts for
    public function on(string $date): static
    {
        return $this->state(fn (array $attributes) => ['period_date' => $date]);
    }

    public function down(): static
    {
        return $this->state(fn (array $attributes) => ['direction' => HabitDirection::Down]);
    }

    public function rewarded(int $xp, int $gold): static
    {
        return $this->state(fn (array $attributes) => ['xp_awarded' => $xp, 'gold_awarded' => $gold]);
    }
}
