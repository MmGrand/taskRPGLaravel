<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HabitMark;
use App\Models\Task;
use App\Models\TaskCompletion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

// One cap for the whole day: completed tasks and habit pluses share progression.rewards.daily_limit
final class DailyRewardLimit
{
    public function reached(int $userId, CarbonImmutable $day): bool
    {
        return $this->rewardedOn($userId, $day) >= config()->integer('progression.rewards.daily_limit');
    }

    // Counted under the character lock, so parallel rewards of one user cannot both take the last slot.
    // Deleted tasks count too: deletion undoes their marks of today, so only earlier days remain
    public function rewardedOn(int $userId, CarbonImmutable $day): int
    {
        $tasks = Task::withTrashed()->where('user_id', $userId)->select('id');
        $rewarded = fn (Builder $query) => $query->where('xp_awarded', '>', 0)->orWhere('gold_awarded', '>', 0);

        return TaskCompletion::query()
            ->whereIn('task_id', $tasks)
            ->where('period_date', $day->toDateString())
            ->where($rewarded)
            ->count()
            + HabitMark::query()
                ->whereIn('task_id', $tasks)
                ->where('period_date', $day->toDateString())
                ->where($rewarded)
                ->count();
    }
}
