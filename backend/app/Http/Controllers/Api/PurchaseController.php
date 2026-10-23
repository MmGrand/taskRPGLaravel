<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\PurchaseRefused;
use App\Http\Controllers\Api\Concerns\ResolvesCharacter;
use App\Http\Controllers\Controller;
use App\Http\Requests\BuyItemRequest;
use App\Http\Resources\AchievementResource;
use App\Http\Resources\CharacterResource;
use App\Models\Item;
use App\Services\AchievementService;
use App\Services\ShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class PurchaseController extends Controller
{
    use ResolvesCharacter;

    public function __construct(
        private readonly ShopService $shop,
        private readonly AchievementService $achievements,
    ) {}

    /**
     * Купить предмет за золото.
     *
     * 422 с причиной в `item_id`: уже куплен, мал уровень, не хватает золота или вещь редкая. В `achievements` — достижения, полученные этой покупкой.
     */
    public function __invoke(BuyItemRequest $request): JsonResponse
    {
        $character = $this->characterOf($request);
        $item = Item::query()->findOrFail($request->integer('item_id'));

        try {
            $this->shop->buy($character, $item);
        } catch (PurchaseRefused $refused) {
            throw ValidationException::withMessages(['item_id' => $refused->getMessage()]);
        }

        // Earned before the hero is read back, so its next goal and a rare item are already there
        $earned = $this->achievements->award($character->user);

        // The purchase changed a locked copy of the character, so read the new gold back
        return CharacterResource::make($character->refresh()->load('equippedItems.item'))
            ->additional(['achievements' => AchievementResource::collection($earned)->resolve($request)])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
