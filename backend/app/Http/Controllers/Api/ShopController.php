<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCharacter;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShopItemResource;
use App\Models\Item;
use App\Services\ProgressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShopController extends Controller
{
    use ResolvesCharacter;

    public function __construct(private readonly ProgressionService $progression) {}

    public function __invoke(Request $request): JsonResponse
    {
        $character = $this->characterOf($request);
        $ownedIds = array_fill_keys($character->inventoryItems()->pluck('item_id')->all(), true);
        $level = $this->progression->progressFor($character->xp)->level;
        $items = Item::query()->orderBy('required_level')->orderBy('id')->get();

        // Each entry needs the owned set and the level, which a resource collection cannot pass on
        return response()->json([
            'data' => $items
                ->map(fn (Item $item): array => (new ShopItemResource($item, $ownedIds, $level))->resolve($request))
                ->all(),
        ]);
    }
}
