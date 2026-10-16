<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\HabitDirection;
use App\Models\User;
use App\ValueObjects\ActivityRecord;
use App\ValueObjects\Streak;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class StreakService
{
    public function __construct(private readonly UserCalendar $calendar) {}

    // A day counts when the user completed a task or marked a habit plus; today does not break the chain until it ends
    public function current(User $user): Streak
    {
        $today = $this->calendar->today($user);
        $days = $this->activeDays($user, $today);
        $activeToday = ($days[0] ?? null) === $today->toDateString();

        return new Streak($this->chainEndingOn($days, $activeToday ? $today : $today->subDay()), $activeToday);
    }

    // The streak a completion made right now belongs to
    public function lengthIncludingToday(User $user): int
    {
        $today = $this->calendar->today($user);

        return $this->chainEndingOn($this->activeDays($user, $today), $today->subDay()) + 1;
    }

    // Days with any activity, in a row or not, and the longest run of them ever, the current one included:
    // a broken streak keeps its record. Both from one read of the history
    public function record(User $user): ActivityRecord
    {
        $days = $this->activeDays($user, $this->calendar->today($user));
        $longest = 0;
        $run = 0;
        $newer = null;

        foreach ($days as $day) {
            $date = CarbonImmutable::parse($day);
            $run = $newer !== null && $newer->subDay()->isSameDay($date) ? $run + 1 : 1;
            $longest = max($longest, $run);
            $newer = $date;
        }

        return new ActivityRecord(count($days), $longest);
    }

    public function longest(User $user): int
    {
        return $this->record($user)->longestStreak;
    }

    public function activeDayCount(User $user): int
    {
        return $this->record($user)->activeDays;
    }

    /**
     * Distinct local days with a completion or a habit plus, newest first. A minus is no activity.
     * Deleted tasks still count: their history is kept.
     *
     * @return list<string>
     */
    private function activeDays(User $user, CarbonImmutable $today): array
    {
        $completions = DB::table('task_completions')
            ->join('tasks', 'tasks.id', '=', 'task_completions.task_id')
            ->where('tasks.user_id', $user->id)
            ->where('task_completions.period_date', '<=', $today->toDateString())
            ->select('task_completions.period_date');
        $pluses = DB::table('habit_marks')
            ->join('tasks', 'tasks.id', '=', 'habit_marks.task_id')
            ->where('tasks.user_id', $user->id)
            ->where('habit_marks.direction', HabitDirection::Up->value)
            ->where('habit_marks.period_date', '<=', $today->toDateString())
            ->select('habit_marks.period_date');

        // UNION drops the days found in both
        return array_values(DB::query()
            ->fromSub($completions->union($pluses), 'active_days')
            ->orderByDesc('period_date')
            ->pluck('period_date')
            ->map(fn (mixed $day): string => (string) $day)
            ->all());
    }

    /**
     * Length of the run of consecutive days that ends on $last; later days are skipped.
     *
     * @param  list<string>  $days  Y-m-d strings, newest first
     */
    private function chainEndingOn(array $days, CarbonImmutable $last): int
    {
        $expected = $last->toDateString();
        $length = 0;

        foreach ($days as $day) {
            if ($day > $expected) {
                continue;
            }

            if ($day !== $expected) {
                break;
            }

            $length++;
            $expected = $last->subDays($length)->toDateString();
        }

        return $length;
    }
}
