<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\EnsuresTaskOwnership;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskCompletionService;
use Illuminate\Http\Request;

final class TaskCompletionController extends Controller
{
    use EnsuresTaskOwnership;

    public function __construct(private readonly TaskCompletionService $completions) {}

    public function store(Request $request, Task $task): TaskResource
    {
        $this->ensureOwnedBy($request, $task);
        $this->completions->complete($task);

        return new TaskResource($task->load('latestCompletion'));
    }

    public function destroy(Request $request, Task $task): TaskResource
    {
        $this->ensureOwnedBy($request, $task);
        $this->completions->undo($task);

        return new TaskResource($task->load('latestCompletion'));
    }
}
