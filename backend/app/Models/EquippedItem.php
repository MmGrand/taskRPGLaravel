<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemSlot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ItemSlot $slot
 * @property CarbonImmutable $equipped_at
 * @property-read Item $item
 */
#[Fillable(['slot', 'item_id', 'equipped_at'])]
final class EquippedItem extends Model
{
    // equipped_at is refreshed on every equip; created/updated timestamps add nothing
    public $timestamps = false;

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot' => ItemSlot::class,
            'equipped_at' => 'immutable_datetime',
        ];
    }
}
