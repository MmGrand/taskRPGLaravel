<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCharacter;
use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryItemResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class InventoryController extends Controller
{
    use ResolvesCharacter;

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $items = $this->characterOf($request)->inventoryItems()
            // Only inventory columns, so the joined items.id does not overwrite inventory_items.id
            ->select('inventory_items.*')
            ->join('items', 'items.id', '=', 'inventory_items.item_id')
            ->orderBy('items.required_level')
            ->orderBy('items.id')
            ->with('item')
            ->get();

        return InventoryItemResource::collection($items);
    }
}
