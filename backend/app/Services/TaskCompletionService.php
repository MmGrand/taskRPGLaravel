<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TaskType;
use App\Models\Task;
use App\Models\TaskCompletion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class TaskCompletionService
{
    public function __construct(private readonly UserCalendar $calendar) {}

    // Idempotent: completing an already completed task changes nothing
    public function complete(Task $task): void
    {
        DB::transaction(function () use ($task): void {
            $this->lock($task);
            $today = $this->calendar->today($task->user);

            if ($this->currentCompletion($task, $today) !== null) {
                return;
            }

            $task->completions()->create([
                'period_date' => $today->toDateString(),
                'completed_at' => now(),
            ]);
        });
    }

    // Only today's mark can be taken back; a todo finished on an earlier day stays done
    public function undo(Task $task): void
    {
        DB::transaction(function () use ($task): void {
            $this->lock($task);

            $task->completions()
                ->where('period_date', $this->calendar->today($task->user)->toDateString())
                ->delete();
        });
    }

    // Serialises parallel requests for the same task; the completion row may not exist yet
    private function lock(Task $task): void
    {
        Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
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
