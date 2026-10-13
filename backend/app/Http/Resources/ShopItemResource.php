<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Item
 */
final class ShopItemResource extends JsonResource
{
    /**
     * @param  array<int, true>  $ownedIds  ids of the items the hero owns, as keys
     */
    public function __construct(Item $item, private readonly array $ownedIds, private readonly int $level)
    {
        parent::__construct($item);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'item' => new ItemResource($this->resource),
            'owned' => isset($this->ownedIds[$this->id]),
            'unlocked' => $this->required_level <= $this->level,
        ];
    }
}
