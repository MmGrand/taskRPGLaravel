<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Beard;
use App\Enums\HairColor;
use App\Enums\HairStyle;
use App\Enums\SkinTone;
use Database\Factories\CharacterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property SkinTone $skin_tone
 * @property HairStyle $hair_style
 * @property HairColor $hair_color
 * @property Beard $beard
 */
#[Fillable(['name', 'skin_tone', 'hair_style', 'hair_color', 'beard'])]
final class Character extends Model
{
    /** @use HasFactory<CharacterFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        ];
    }
}
