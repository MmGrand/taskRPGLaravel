<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemSlot;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property ItemSlot $slot
 * @property int $sprite_col
 * @property int $sprite_row
 * @property int $required_level
 * @property int $price
 * @property bool $is_starter
 * @property bool $is_rare
 */
#[Fillable(['code', 'name', 'slot', 'sprite_col', 'sprite_row', 'required_level', 'price', 'is_starter', 'is_rare'])]
final class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot' => ItemSlot::class,
            'sprite_col' => 'integer',
            'sprite_row' => 'integer',
            'required_level' => 'integer',
            'price' => 'integer',
            'is_starter' => 'boolean',
            'is_rare' => 'boolean',
        ];
    }
}
