<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Task;
use App\Models\User;
use App\Services\UserCalendar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
final class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $isCompleted = $this->isCompletedOn(app(UserCalendar::class)->today($user));

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type->value,
            'is_completed' => $isCompleted,
            'completed_at' => $isCompleted ? $this->latestCompletion?->completed_at->toIso8601String() : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
