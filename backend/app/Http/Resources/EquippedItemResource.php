<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EquippedItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EquippedItem
 */
final class EquippedItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slot' => $this->slot->value,
            'item' => new ItemResource($this->item),
        ];
    }
}
