<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PurchaseRefused;
use App\Models\Character;
use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final class ShopService
{
    public function __construct(private readonly ProgressionService $progression) {}

    // Spends gold and adds the item to the inventory, or refuses without changing anything
    public function buy(Character $character, Item $item): void
    {
        DB::transaction(function () use ($character, $item): void {
            // Parallel purchases and rewards change the same gold; the lock makes the checks hold until commit
            $locked = Character::query()->whereKey($character->id)->lockForUpdate()->firstOrFail();

            if ($locked->inventoryItems()->where('item_id', $item->id)->exists()) {
                throw PurchaseRefused::alreadyOwned();
            }

            if ($this->progression->progressFor($locked->xp)->level < $item->required_level) {
                throw PurchaseRefused::levelTooLow($item);
            }

            if ($locked->gold < $item->price) {
                throw PurchaseRefused::notEnoughGold($item, $locked->gold);
            }

            $locked->gold -= $item->price;
            $locked->save();
            $locked->inventoryItems()->create(['item_id' => $item->id, 'acquired_at' => now()]);
        });
    }

    /**
     * Unlocked items the player has not seen in the shop yet and does not own.
     *
     * @return Collection<int, Item>
     */
    public function newItemsFor(Character $character): Collection
    {
        return Item::query()
            ->where('required_level', '>', $character->shop_seen_level)
            ->where('required_level', '<=', $this->progression->progressFor($character->xp)->level)
            ->whereNotExists(fn (QueryBuilder $owned) => $owned->from('inventory_items')
                ->whereColumn('inventory_items.item_id', 'items.id')
                ->where('inventory_items.character_id', $character->id))
            ->orderBy('required_level')
            ->orderBy('id')
            ->get();
    }

    // Remembers the highest level seen, so losing a level and regaining it brings no false "new"
    public function markSeen(Character $character): void
    {
        $character->shop_seen_level = max(
            $character->shop_seen_level,
            $this->progression->progressFor($character->xp)->level,
        );
        $character->save();
    }
}
