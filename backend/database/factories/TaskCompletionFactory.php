<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskCompletion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskCompletion>
 */
final class TaskCompletionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'period_date' => now()->toDateString(),
            'completed_at' => now(),
        ];
    }

    // The user's local calendar day (Y-m-d) the completion counts for
    public function on(string $date): static
    {
        return $this->state(fn (array $attributes) => ['period_date' => $date]);
    }
}
