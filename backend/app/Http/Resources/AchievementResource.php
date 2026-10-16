<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\ValueObjects\AchievementProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property AchievementProgress $resource
 */
final class AchievementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $progress = $this->resource;

        return [
            'code' => $progress->definition->code,
            'title' => $progress->definition->title,
            'description' => $progress->definition->description,
            'current' => $progress->current,
            'target' => $progress->target,
            'earned_at' => $progress->earnedAt?->toIso8601String(),
            'reward_item' => $progress->rewardItem === null ? null : (new ItemResource($progress->rewardItem))->resolve($request),
        ];
    }
}
