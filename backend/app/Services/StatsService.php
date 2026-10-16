<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\HabitDirection;
use App\Enums\TaskType;
use App\Models\Task;
use App\Models\User;
use App\ValueObjects\DailyCompletionRate;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class StatsService
{
    public const int HEATMAP_WEEKS = 12;

    public const int RATE_DAYS = 30;

    public function __construct(private readonly UserCalendar $calendar) {}

    /**
     * Completions and habit pluses per day from the Monday HEATMAP_WEEKS-1 weeks back to today, every day present.
     * Deleted tasks and completions past the reward limit count too: they were done. A minus is not.
     *
     * @return list<array{date: string, count: int}>
     */
    public function heatmap(User $user): array
    {
        $today = $this->calendar->today($user);
        $from = $today->startOfWeek(CarbonInterface::MONDAY)->subWeeks(self::HEATMAP_WEEKS - 1);
        $period = [$from->toDateString(), $today->toDateString()];

        $completions = DB::table('task_completions')
            ->join('tasks', 'tasks.id', '=', 'task_completions.task_id')
            ->where('tasks.user_id', $user->id)
            ->whereBetween('task_completions.period_date', $period)
            ->select('task_completions.period_date as day');
        $pluses = DB::table('habit_marks')
            ->join('tasks', 'tasks.id', '=', 'habit_marks.task_id')
            ->where('tasks.user_id', $user->id)
            ->where('habit_marks.direction', HabitDirection::Up->value)
            ->whereBetween('habit_marks.period_date', $period)
            ->select('habit_marks.period_date as day');

        /** @var array<string, int> $counts */
        $counts = DB::query()
            ->fromSub($completions->unionAll($pluses), 'done')
            ->groupBy('day')
            ->selectRaw('day, count(*) as total')
            ->pluck('total', 'day')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        $days = [];
        for ($day = $from; $day <= $today; $day = $day->addDay()) {
            $date = $day->toDateString();
            $days[] = ['date' => $date, 'count' => $counts[$date] ?? 0];
        }

        return $days;
    }

    /**
     * Pluses and minuses of every habit over the RATE_DAYS days up to today, today included, in list order.
     * Deleted habits are left out: there is nothing to act on. One query for any number of habits.
     *
     * @return list<array{id: int, title: string, up: int, down: int}>
     */
    public function habits(User $user): array
    {
        $today = $this->calendar->today($user);
        $period = [$today->subDays(self::RATE_DAYS - 1)->toDateString(), $today->toDateString()];
        $marks = fn (HabitDirection $direction) => fn (Builder $query) => $query
            ->where('direction', $direction)
            ->whereBetween('period_date', $period);

        return array_values($user->tasks()
            ->where('type', TaskType::Habit)
            ->withCount([
                'habitMarks as marks_up' => $marks(HabitDirection::Up),
                'habitMarks as marks_down' => $marks(HabitDirection::Down),
            ])
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (Task $habit): array => [
                'id' => $habit->id,
                'title' => $habit->title,
                'up' => (int) $habit->getAttribute('marks_up'),
                'down' => (int) $habit->getAttribute('marks_down'),
            ])
            ->all());
    }

    /**
     * Dailies due and done over the RATE_DAYS finished days before today. A daily is due on a day it existed
     * on (created by then, not deleted yet) that its current schedule includes; there is no schedule history.
     */
    public function dailyCompletionRate(User $user): DailyCompletionRate
    {
        $today = $this->calendar->today($user);
        $first = $today->subDays(self::RATE_DAYS);
        $dailies = Task::withTrashed()
            ->where('user_id', $user->id)
            ->where('type', TaskType::Daily)
            ->get();
        $done = $this->completedDays($dailies->modelKeys(), $first, $today->subDay());
        /** @var array<int, array{0: string|null, 1: string|null}> $lifetimes */
        $lifetimes = $dailies->mapWithKeys(fn (Task $daily): array => [$daily->id => $this->lifetime($daily, $user)])->all();

        $scheduled = 0;
        $completed = 0;

        for ($day = $first; $day < $today; $day = $day->addDay()) {
            $date = $day->toDateString();

            foreach ($dailies as $daily) {
                [$created, $deleted] = $lifetimes[$daily->id];
                // Deleting a task takes back the mark of that day, so the day it was deleted on is not counted
                $existed = $created !== null && $created <= $date && ($deleted === null || $deleted > $date);

                if (! $existed || ! $daily->isScheduledOn($day)) {
                    continue;
                }

                $scheduled++;
                $completed += isset($done["{$daily->id}|{$date}"]) ? 1 : 0;
            }
        }

        return new DailyCompletionRate(self::RATE_DAYS, $scheduled, $completed);
    }

    /**
     * @param  array<int, int|string>  $taskIds
     * @return array<string, bool> keyed by "taskId|Y-m-d", always true
     */
    private function completedDays(array $taskIds, CarbonImmutable $first, CarbonImmutable $last): array
    {
        return DB::table('task_completions')
            ->whereIn('task_id', $taskIds)
            ->whereBetween('period_date', [$first->toDateString(), $last->toDateString()])
            ->get(['task_id', 'period_date'])
            ->mapWithKeys(fn (object $row): array => ["{$row->task_id}|{$row->period_date}" => true])
            ->all();
    }

    /**
     * The days the task was created and deleted on the user's calendar, worked out once per task.
     * The model dates are mutable, so they are copied before the zone changes.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function lifetime(Task $task, User $user): array
    {
        return [
            $task->created_at?->toImmutable()->setTimezone($user->timezone)->toDateString(),
            $task->deleted_at?->toImmutable()->setTimezone($user->timezone)->toDateString(),
        ];
    }
}
