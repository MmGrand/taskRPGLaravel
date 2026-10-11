<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Character;
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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'appearance' => [
                'skin_tone' => $this->skin_tone->value,
                'hair_style' => $this->hair_style->value,
                'hair_color' => $this->hair_color->value,
                'beard' => $this->beard->value,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
