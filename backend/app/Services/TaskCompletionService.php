<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TaskType;
use App\Models\Character;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\ValueObjects\Reward;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class TaskCompletionService
{
    public function __construct(
        private readonly UserCalendar $calendar,
        private readonly StreakService $streaks,
        private readonly RewardService $rewards,
    ) {}

    // Idempotent: completing an already completed task changes nothing and gives no reward.
    // Returns what the character got, or null when nothing was given
    public function complete(Task $task): ?Reward
    {
        return DB::transaction(function () use ($task): ?Reward {
            $this->lock($task);
            $today = $this->calendar->today($task->user);

            if ($this->currentCompletion($task, $today) !== null) {
                return null;
            }

            $character = $this->lockCharacter($task);
            $reward = $character === null
                ? Reward::none()
                : $this->rewards->rewardFor($this->streaks->lengthIncludingToday($task->user));

            $task->completions()->create([
                'period_date' => $today->toDateString(),
                'completed_at' => now(),
                'xp_awarded' => $reward->xp,
                'gold_awarded' => $reward->gold,
            ]);

            if ($character === null) {
                return null;
            }

            $this->rewards->grant($character, $reward);

            return $reward;
        });
    }

    // Only today's mark can be taken back, together with what it gave; a todo finished on an earlier day stays done.
    // Returns what was taken from the character, or null when nothing changed
    public function undo(Task $task): ?Reward
    {
        return DB::transaction(function () use ($task): ?Reward {
            $this->lock($task);

            $completion = $task->completions()
                ->where('period_date', $this->calendar->today($task->user)->toDateString())
                ->first();

            if ($completion === null) {
                return null;
            }

            $character = $this->lockCharacter($task);
            $completion->delete();

            return $character === null ? null : $this->rewards->revoke($character, $completion->reward());
        });
    }

    // Serialises parallel requests for the same task; the completion row may not exist yet
    private function lock(Task $task): void
    {
        Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
    }

    // Rewards of parallel completions of different tasks add up on one row; always taken after the task lock
    private function lockCharacter(Task $task): ?Character
    {
        return Character::query()->where('user_id', $task->user_id)->lockForUpdate()->first();
    }

    // The completion that makes the task count as done today, if any
    private function currentCompletion(Task $task, CarbonImmutable $today): ?TaskCompletion
    {
        $query = $task->completions();

        if ($task->type === TaskType::Daily) {
            $query->where('period_date', $today->toDateString());
        }

        return $query->first();
    }
}
