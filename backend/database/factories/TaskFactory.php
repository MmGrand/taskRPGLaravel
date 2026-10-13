<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskType;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Task>
 */
final class TaskFactory extends Factory
{
    private const int MAX_TITLE_LENGTH = 120;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => Str::limit(fake()->sentence(3), self::MAX_TITLE_LENGTH, ''),
            'type' => fake()->randomElement(TaskType::cases()),
        ];
    }

    public function daily(): static
    {
        return $this->state(fn (array $attributes) => ['type' => TaskType::Daily]);
    }

    public function todo(): static
    {
        return $this->state(fn (array $attributes) => ['type' => TaskType::Todo]);
    }
}
