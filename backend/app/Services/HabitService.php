<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\HabitDirection;
use App\Enums\TaskType;
use App\Exceptions\HabitMarkRefused;
use App\Models\Character;
use App\Models\HabitMark;
use App\Models\Task;
use App\ValueObjects\Reward;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

// Pluses and minuses of a habit. A plus is rewarded like a completion; a minus is only counted, never punished
final class HabitService
{
    public function __construct(
        private readonly UserCalendar $calendar,
        private readonly StreakService $streaks,
        private readonly RewardService $rewards,
        private readonly DailyRewardLimit $limit,
    ) {}

    // Returns what the character got for a plus, or null for a minus, which changes nothing on the hero.
    // Throws HabitMarkRefused for a task that is not a habit or past the marks a day allows
    public function mark(Task $task, HabitDirection $direction): ?Reward
    {
        return DB::transaction(function () use ($task, $direction): ?Reward {
            $locked = $this->lock($task);
            $today = $this->calendar->today($task->user);

            if ($this->marksOn($locked, $today)->count() >= config()->integer('habits.max_marks_per_day')) {
                throw HabitMarkRefused::tooMany();
            }

            $character = $this->lockCharacter($task);
            $reward = match (true) {
                $direction === HabitDirection::Down, $character === null => Reward::none(),
                $this->limit->reached($task->user_id, $today),
                $this->rewardedPlusesOn($locked, $today) >= config()->integer('habits.rewarded_per_day') => Reward::capped(),
                default => $this->rewards->rewardFor($this->streaks->lengthIncludingToday($task->user), $locked->difficulty),
            };

            $locked->habitMarks()->create([
                'period_date' => $today->toDateString(),
                'direction' => $direction,
                'xp_awarded' => $reward->xp,
                'gold_awarded' => $reward->gold,
                'created_at' => now(),
            ]);

            if ($direction === HabitDirection::Down || $character === null) {
                return null;
            }

            $this->rewards->grant($character, $reward);

            return $reward;
        });
    }

    // Takes back the latest mark of today with what it gave, for a mistaken tap; earlier days stay.
    // Returns what was taken from the character, or null when there was nothing to undo
    public function undoLast(Task $task): ?Reward
    {
        return DB::transaction(function () use ($task): ?Reward {
            $locked = $this->lock($task);
            $mark = $this->marksOn($locked, $this->calendar->today($task->user))->orderByDesc('id')->first();

            if ($mark === null) {
                return null;
            }

            $character = $this->lockCharacter($task);
            $mark->delete();

            return $character === null ? null : $this->rewards->revoke($character, $mark->reward());
        });
    }

    // Deleting a habit takes back what its marks of today gave, like deleting a task completed today.
    // Runs inside the caller's transaction. Returns what was taken, or null when nothing was rewarded
    public function undoToday(Task $task): ?Reward
    {
        $locked = $this->lock($task);
        $marks = $this->marksOn($locked, $this->calendar->today($task->user))->get();

        if ($marks->isEmpty()) {
            return null;
        }

        $character = $this->lockCharacter($task);
        $given = new Reward((int) $marks->sum('xp_awarded'), (int) $marks->sum('gold_awarded'));
        $this->marksOn($locked, $this->calendar->today($task->user))->delete();

        if ($character === null || ($given->xp === 0 && $given->gold === 0)) {
            return null;
        }

        return $this->rewards->revoke($character, $given);
    }

    // Serialises parallel marks of one habit, so the per-habit limits hold; refuses any other task type
    private function lock(Task $task): Task
    {
        $locked = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

        if ($locked->type !== TaskType::Habit) {
            throw HabitMarkRefused::notHabit();
        }

        return $locked;
    }

    // Always taken after the task lock, like in TaskCompletionService
    private function lockCharacter(Task $task): ?Character
    {
        return Character::query()->where('user_id', $task->user_id)->lockForUpdate()->first();
    }

    /**
     * @return HasMany<HabitMark, Task>
     */
    private function marksOn(Task $habit, CarbonImmutable $day): HasMany
    {
        return $habit->habitMarks()->where('period_date', $day->toDateString());
    }

    private function rewardedPlusesOn(Task $habit, CarbonImmutable $day): int
    {
        return $this->marksOn($habit, $day)
            ->where('direction', HabitDirection::Up)
            ->where(fn (Builder $query) => $query->where('xp_awarded', '>', 0)->orWhere('gold_awarded', '>', 0))
            ->count();
    }
}
