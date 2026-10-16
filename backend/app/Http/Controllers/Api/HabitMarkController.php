<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\HabitDirection;
use App\Http\Controllers\Api\Concerns\EnsuresTaskOwnership;
use App\Http\Controllers\Api\Concerns\RespondsWithTaskChange;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHabitMarkRequest;
use App\Models\Task;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\HabitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class HabitMarkController extends Controller
{
    use EnsuresTaskOwnership, RespondsWithTaskChange;

    public function __construct(
        private readonly HabitService $habits,
        private readonly AchievementService $achievements,
    ) {}

    // A task that is not a habit throws HabitMarkRefused, which bootstrap/app.php turns into a 422
    public function store(StoreHabitMarkRequest $request, Task $task): JsonResponse
    {
        $this->ensureOwnedBy($request, $task);
        /** @var User $user */
        $user = $request->user();
        $direction = $request->direction();
        $reward = $this->habits->mark($task, $direction);
        // A minus is no activity, so it cannot bring an achievement closer
        $earned = $direction === HabitDirection::Up ? $this->achievements->award($user) : [];

        return $this->respondWithChange($request, $task, $reward, $earned)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    // Takes back the latest mark of today, for a mistaken tap
    public function destroy(Request $request, Task $task): JsonResponse
    {
        $this->ensureOwnedBy($request, $task);

        return $this->respondWithChange($request, $task, $this->habits->undoLast($task)?->negated())->response();
    }
}
