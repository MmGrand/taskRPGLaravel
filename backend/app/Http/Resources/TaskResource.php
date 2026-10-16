<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\TaskType;
use App\Models\Task;
use App\Models\User;
use App\Services\UserCalendar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
final class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $today = app(UserCalendar::class)->today($user);
        $isCompleted = $this->isCompletedOn($today);
        $isHabit = $this->type === TaskType::Habit;

        // A list loads the counts in one query; a single task answered after a change loads its own
        if ($isHabit && $this->habit_up_today === null) {
            $this->resource->loadHabitCounts($today);
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type->value,
            'difficulty' => $this->difficulty->value,
            'weekdays' => $this->weekdays->isoDays(),
            // A daily off its schedule today cannot be completed; a todo and a habit are always due
            'scheduled_today' => $this->isScheduledOn($today),
            'is_completed' => $isCompleted,
            // Deleting the task gives this back; false past the daily limit and for a todo done earlier
            'rewarded_today' => $isHabit ? $this->habit_rewarded_today > 0 : $this->isRewardedOn($today),
            // Today's marks of a habit; null for the other types
            'habit' => $isHabit
                ? ['up_today' => (int) $this->habit_up_today, 'down_today' => (int) $this->habit_down_today]
                : null,
            'completed_at' => $isCompleted ? $this->latestCompletion?->completed_at->toIso8601String() : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
