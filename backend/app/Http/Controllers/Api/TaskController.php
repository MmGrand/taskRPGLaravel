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

    /**
     * Задачи в сохранённом порядке.
     *
     * С отметкой «выполнено сегодня». Выполненные разовые скрываются со следующего дня.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $today = $this->calendar->today($user);
        $tasks = $user->tasks()
            ->activeOn($today)
            ->with('latestCompletion')
            ->withHabitCounts($today)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return TaskResource::collection($tasks);
    }

    /**
     * Создать задачу.
     *
     * Не больше 100 активных задач.
     */
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
            // The model puts it last; the lock above keeps parallel creations from taking the same place
            $task = $user->tasks()->create($request->taskAttributes());

            return $task;
        });

        return TaskResource::make($task->load('latestCompletion'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Изменить задачу.
     *
     * Название, сложность и дни недели (дни есть только у ежедневки).
     */
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $this->ensureOwnedBy($request, $task);
        $task->update($request->taskAttributes());

        return new TaskResource($task->load('latestCompletion'));
    }

    /**
     * Удалить задачу.
     *
     * Сегодняшние отметки снимаются вместе с наградой, как при снятии галочки. Прошлые дни остаются в истории.
     */
    public function destroy(Request $request, Task $task): TaskResource
    {
        $this->ensureOwnedBy($request, $task);

        return $this->respondWithChange($request, $task, $this->completions->delete($task)?->negated());
    }
}
