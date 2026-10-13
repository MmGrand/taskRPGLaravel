<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ItemSlot;
use App\Exceptions\ItemDoesNotFitSlot;
use App\Models\Character;
use App\Models\EquippedItem;
use App\Models\Item;

final class EquipmentService
{
    // Equipping an occupied slot replaces the worn item; repeating the same item changes nothing
    public function equip(Character $character, ItemSlot $slot, Item $item): void
    {
        if ($item->slot !== $slot) {
            throw ItemDoesNotFitSlot::for($item, $slot);
        }

        EquippedItem::query()->upsert(
            [[
                'character_id' => $character->id,
                'slot' => $slot->value,
                'item_id' => $item->id,
                'equipped_at' => now(),
            ]],
            ['character_id', 'slot'],
            ['item_id', 'equipped_at'],
        );
    }

    public function unequip(Character $character, ItemSlot $slot): void
    {
        $character->equippedItems()->where('slot', $slot)->delete();
    }
}
