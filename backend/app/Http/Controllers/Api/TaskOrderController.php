<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderTasksRequest;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

final class TaskOrderController extends Controller
{
    /**
     * Сохранить порядок задач.
     *
     * `ids` в новом порядке, ответ 204. Чужой или удалённый id — 422.
     */
    // The listed tasks swap places among themselves: they take the positions they held, in the new order.
    // Tasks left out keep theirs, so reordering one list never moves another, even when two saves cross
    public function __invoke(ReorderTasksRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        /** @var list<int> $ids */
        $ids = array_map(intval(...), $request->validated('ids'));

        // Same lock as creation, so a new task cannot take a place in the middle of a reorder
        DB::transaction(function () use ($user, $ids): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            /** @var list<int> $places */
            $places = $user->tasks()->whereKey($ids)->orderBy('position')->pluck('position')->all();

            // One statement for the whole list, every value bound. The cast keeps Postgres from typing the places as text
            $cases = implode(' ', array_fill(0, count($ids), 'when ? then cast(? as integer)'));
            $placeholders = implode(', ', array_fill(0, count($ids), '?'));
            $pairs = array_merge(...array_map(fn (int $id, int $place): array => [$id, $place], $ids, $places));

            DB::update(
                "update tasks set position = case id {$cases} end, updated_at = ? where user_id = ? and id in ({$placeholders})",
                [...$pairs, now(), $user->id, ...$ids],
            );
        });

        return response()->noContent();
    }
}
