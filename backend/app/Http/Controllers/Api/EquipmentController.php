<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ItemSlot;
use App\Http\Controllers\Api\Concerns\ResolvesCharacter;
use App\Http\Controllers\Controller;
use App\Http\Requests\EquipItemRequest;
use App\Http\Resources\CharacterResource;
use App\Models\Item;
use App\Services\EquipmentService;
use Illuminate\Http\Request;

final class EquipmentController extends Controller
{
    use ResolvesCharacter;

    public function __construct(private readonly EquipmentService $equipment) {}

    /**
     * Надеть вещь из инвентаря.
     */
    public function update(EquipItemRequest $request, ItemSlot $slot): CharacterResource
    {
        $character = $this->characterOf($request);
        $item = Item::query()->findOrFail($request->integer('item_id'));
        $this->equipment->equip($character, $slot, $item);

        return new CharacterResource($character->load('equippedItems.item'));
    }

    /**
     * Снять вещь со слота.
     */
    public function destroy(Request $request, ItemSlot $slot): CharacterResource
    {
        $character = $this->characterOf($request);
        $this->equipment->unequip($character, $slot);

        return new CharacterResource($character->load('equippedItems.item'));
    }
}
