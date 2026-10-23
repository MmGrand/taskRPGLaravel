<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\EnsuresTaskOwnership;
use App\Http\Controllers\Api\Concerns\RespondsWithTaskChange;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\TaskCompletionService;
use Illuminate\Http\Request;

final class TaskCompletionController extends Controller
{
    use EnsuresTaskOwnership, RespondsWithTaskChange;

    public function __construct(
        private readonly TaskCompletionService $completions,
        private readonly AchievementService $achievements,
    ) {}

    /**
     * Отметить задачу выполненной.
     *
     * Начисляет XP и золото, повтор ничего не меняет. Ежедневка не по расписанию или привычка — 422. `reward.capped: true` — дневной лимит наград исчерпан, отметка засчитана без награды.
     */
    public function store(Request $request, Task $task): TaskResource
    {
        $this->ensureOwnedBy($request, $task);
        /** @var User $user */
        $user = $request->user();

        // A daily off its schedule throws CompletionRefused, which bootstrap/app.php turns into a 422
        $reward = $this->completions->complete($task);

        // Earned before the hero is read for the answer, so its next goal already moved on
        return $this->respondWithChange($request, $task, $reward, $this->achievements->award($user));
    }

    /**
     * Снять отметку за сегодня.
     *
     * Забирает ровно ту награду, которую дала отметка (`reward` с минусом).
     */
    // An undo never takes an achievement back, so there is nothing to check
    public function destroy(Request $request, Task $task): TaskResource
    {
        $this->ensureOwnedBy($request, $task);

        return $this->respondWithChange($request, $task, $this->completions->undo($task)?->negated());
    }
}
