<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCharacter;
use App\Http\Controllers\Controller;
use App\Http\Resources\CharacterResource;
use App\Services\ShopService;
use Illuminate\Http\Request;

final class ShopVisitController extends Controller
{
    use ResolvesCharacter;

    public function __construct(private readonly ShopService $shop) {}

    // Opening the shop clears its "new" mark; repeating it changes nothing
    public function __invoke(Request $request): CharacterResource
    {
        $character = $this->characterOf($request);
        $this->shop->markSeen($character);

        return new CharacterResource($character->load('equippedItems.item'));
    }
}
