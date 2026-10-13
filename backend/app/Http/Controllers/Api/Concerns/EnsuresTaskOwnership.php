<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Task;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait EnsuresTaskOwnership
{
    // Someone else's task answers 404, not 403, so other users' task ids do not leak
    private function ensureOwnedBy(Request $request, Task $task): void
    {
        abort_unless($task->user()->is($request->user()), Response::HTTP_NOT_FOUND);
    }
}
