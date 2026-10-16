<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $code
 * @property CarbonImmutable $earned_at
 */
#[Fillable(['code', 'earned_at'])]
final class UserAchievement extends Model
{
    // Earned once and never changed
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'earned_at' => 'immutable_datetime',
        ];
    }
}
