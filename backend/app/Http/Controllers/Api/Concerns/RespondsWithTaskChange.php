<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use App\Http\Resources\CharacterResource;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\User;
use App\ValueObjects\Reward;
use Illuminate\Http\Request;

trait RespondsWithTaskChange
{
    // The hero comes along so the client updates XP, gold and streak without another request.
    // The reward is what this request changed: positive for a completion, negative for an undo or a deletion
    private function respondWithChange(Request $request, Task $task, ?Reward $change): TaskResource
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
