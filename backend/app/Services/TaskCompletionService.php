<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TaskType;
use App\Exceptions\CompletionRefused;
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
        private readonly DailyRewardLimit $limit,
        private readonly HabitService $habits,
    ) {}

    // Idempotent: completing an already completed task changes nothing and gives no reward.
    // Returns what the character got, or null when nothing was given.
    // Throws CompletionRefused when a daily is not on today's schedule or the task is a habit
    public function complete(Task $task): ?Reward
    {
        return DB::transaction(function () use ($task): ?Reward {
            $locked = $this->lock($task);
            $today = $this->calendar->today($task->user);

            // A habit is marked plus or minus, never done
            if ($locked->type === TaskType::Habit) {
                throw CompletionRefused::habit();
            }

            if ($this->currentCompletion($task, $today) !== null) {
                return null;
            }

            // Checked after the idempotency return: a task done today stays done even if the day left its schedule
            if (! $locked->isScheduledOn($today)) {
                throw CompletionRefused::notScheduled();
            }

            $character = $this->lockCharacter($task);
            $reward = match (true) {
                $character === null => Reward::none(),
                $this->limit->reached($task->user_id, $today) => Reward::capped(),
                default => $this->rewards->rewardFor($this->streaks->lengthIncludingToday($task->user), $locked->difficulty),
            };

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

    // Deleting a task takes back today's mark like an undo, so create → complete → delete earns nothing;
    // a habit gives back what its marks of today gave. Earlier days stay in the history and keep the streak.
    // Returns what was taken, or null
    public function delete(Task $task): ?Reward
    {
        return DB::transaction(function () use ($task): ?Reward {
            $taken = $task->type === TaskType::Habit ? $this->habits->undoToday($task) : $this->undo($task);
            $task->delete();

            return $taken;
        });
    }

    // Serialises parallel requests for the same task; the completion row may not exist yet.
    // Returns the row read under the lock: the schedule may have changed since the route bound the task
    private function lock(Task $task): Task
    {
        return Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
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
