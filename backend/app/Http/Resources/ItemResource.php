<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Item
 */
final class ItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'slot' => $this->slot->value,
            'sprite' => ['col' => $this->sprite_col, 'row' => $this->sprite_row],
            'required_level' => $this->required_level,
            'price' => $this->price,
            // Given for an achievement, never sold
            'is_rare' => $this->is_rare,
        ];
    }
}
