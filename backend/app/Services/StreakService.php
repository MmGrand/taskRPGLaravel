<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\ValueObjects\Streak;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class StreakService
{
    public function __construct(private readonly UserCalendar $calendar) {}

    // A day counts when the user completed at least one task; today does not break the chain until it ends
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

    /**
     * Distinct local days with a completion, newest first. Deleted tasks still count: their history is kept.
     *
     * @return list<string>
     */
    private function activeDays(User $user, CarbonImmutable $today): array
    {
        return array_values(DB::table('task_completions')
            ->join('tasks', 'tasks.id', '=', 'task_completions.task_id')
            ->where('tasks.user_id', $user->id)
            ->where('task_completions.period_date', '<=', $today->toDateString())
            ->distinct()
            ->orderByDesc('task_completions.period_date')
            ->pluck('task_completions.period_date')
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
