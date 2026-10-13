<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\EnsuresTaskOwnership;
use App\Http\Controllers\Controller;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskCompletionService;
use App\ValueObjects\Reward;
use Illuminate\Http\Request;

final class TaskCompletionController extends Controller
{
    use EnsuresTaskOwnership;

    public function __construct(private readonly TaskCompletionService $completions) {}

    public function store(Request $request, Task $task): TaskResource
    {
        $this->ensureOwnedBy($request, $task);

        return $this->respond($request, $task, $this->completions->complete($task));
    }

    public function destroy(Request $request, Task $task): TaskResource
    {
        $this->ensureOwnedBy($request, $task);

        return $this->respond($request, $task, $this->completions->undo($task)?->negated());
    }

    // The hero comes along so the client updates XP, gold and streak without another request.
    // The reward is what this request changed: positive for a completion, negative for an undo
    private function respond(Request $request, Task $task, ?Reward $change): TaskResource
    {
        /** @var User $user */
        $user = $request->user();
        $character = $user->load('character.equippedItems.item')->character;

        return (new TaskResource($task->load('latestCompletion')))->additional([
            'character' => $character === null ? null : (new CharacterResource($character))->resolve($request),
            'reward' => $change?->toArray(),
        ]);
    }
}
