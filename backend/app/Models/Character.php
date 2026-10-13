<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Beard;
use App\Enums\HairColor;
use App\Enums\HairStyle;
use App\Enums\SkinTone;
use Database\Factories\CharacterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property SkinTone $skin_tone
 * @property HairStyle $hair_style
 * @property HairColor $hair_color
 * @property Beard $beard
 * @property int $xp
 * @property int $gold
 * @property int $shop_seen_level
 * @property-read User $user
 * @property-read Collection<int, EquippedItem> $equippedItems
 */
#[Fillable(['name', 'skin_tone', 'hair_style', 'hair_color', 'beard'])]
final class Character extends Model
{
    /** @use HasFactory<CharacterFactory> */
    use HasFactory;

    // Mirrors the column defaults, so a just created character knows its XP without a reload
    protected $attributes = [
        'xp' => 0,
        'gold' => 0,
        'shop_seen_level' => 1,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<InventoryItem, $this>
     */
    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    /**
     * @return HasMany<EquippedItem, $this>
     */
    public function equippedItems(): HasMany
    {
        return $this->hasMany(EquippedItem::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'skin_tone' => SkinTone::class,
            'hair_style' => HairStyle::class,
            'hair_color' => HairColor::class,
            'beard' => Beard::class,
            'xp' => 'integer',
            'gold' => 'integer',
            'shop_seen_level' => 'integer',
        ];
    }
}
