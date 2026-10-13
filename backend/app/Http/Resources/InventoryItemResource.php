<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryItem
 */
final class InventoryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'item' => new ItemResource($this->item),
            'acquired_at' => $this->acquired_at->toIso8601String(),
        ];
    }
}
