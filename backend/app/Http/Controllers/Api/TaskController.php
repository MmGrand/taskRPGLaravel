<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\EnsuresTaskOwnership;
use App\Http\Controllers\Api\Concerns\RespondsWithTaskChange;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskCompletionService;
use App\Services\UserCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class TaskController extends Controller
{
    use EnsuresTaskOwnership, RespondsWithTaskChange;

    private const string LIMIT_REACHED = 'Task limit reached.';

    public function __construct(
        private readonly UserCalendar $calendar,
        private readonly TaskCompletionService $completions,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $tasks = $user->tasks()
            ->activeOn($this->calendar->today($user))
            ->with('latestCompletion')
            ->orderBy('id')
            ->get();

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // The user row lock serialises parallel creations, so a race cannot pass the limit
        $task = DB::transaction(function () use ($request, $user): Task {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            // Only tasks on the list count: finished todos of earlier days are hidden and cannot be deleted
            abort_if(
                $user->tasks()->activeOn($this->calendar->today($user))->count() >= Config::integer('tasks.max_active_per_user'),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                self::LIMIT_REACHED,
            );

            /** @var Task $task */
            $task = $user->tasks()->create($request->validated());

            return $task;
        });

        return TaskResource::make($task->load('latestCompletion'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $this->ensureOwnedBy($request, $task);
        $task->update($request->validated());

        return new TaskResource($task->load('latestCompletion'));
    }

    public function destroy(Request $request, Task $task): TaskResource
    {
        $this->ensureOwnedBy($request, $task);

        return $this->respondWithChange($request, $task, $this->completions->delete($task)?->negated());
    }
}
