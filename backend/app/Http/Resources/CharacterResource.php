<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Character;
use App\Services\ProgressionService;
use App\Services\StreakService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Character
 */
final class CharacterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $progress = app(ProgressionService::class)->progressFor($this->xp);
        $streak = app(StreakService::class)->current($this->user);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'appearance' => [
                'skin_tone' => $this->skin_tone->value,
                'hair_style' => $this->hair_style->value,
                'hair_color' => $this->hair_color->value,
                'beard' => $this->beard->value,
            ],
            'progress' => [
                'level' => $progress->level,
                'xp_total' => $progress->xpTotal,
                'xp_in_level' => $progress->xpInLevel,
                'xp_to_next_level' => $progress->xpToNextLevel,
            ],
            'gold' => $this->gold,
            'streak' => [
                'days' => $streak->days,
                'active_today' => $streak->activeToday,
            ],
            'equipment' => EquippedItemResource::collection($this->equippedItems),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
