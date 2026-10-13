<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\TaskType;
use App\Http\Controllers\Api\Concerns\EnsuresTaskOwnership;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\User;
use App\Services\UserCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

final class TaskController extends Controller
{
    use EnsuresTaskOwnership;

    private const string LIMIT_REACHED = 'Task limit reached.';

    public function __construct(private readonly UserCalendar $calendar) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $today = $this->calendar->today($user)->toDateString();

        $tasks = $user->tasks()
            // Todos completed on an earlier day drop off the list
            ->where(fn (Builder $query) => $query
                ->where('type', TaskType::Daily)
                ->orWhereDoesntHave('completions', fn (Builder $completions) => $completions
                    ->where('period_date', '<', $today)))
            ->with('latestCompletion')
            ->orderBy('id')
            ->get();

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_if(
            $user->tasks()->count() >= Config::integer('tasks.max_active_per_user'),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::LIMIT_REACHED,
        );

        /** @var Task $task */
        $task = $user->tasks()->create($request->validated());

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

    public function destroy(Request $request, Task $task): Response
    {
        $this->ensureOwnedBy($request, $task);
        $task->delete();

        return response()->noContent();
    }
}
