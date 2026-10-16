<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HabitDirection;
use App\ValueObjects\Reward;
use Carbon\CarbonImmutable;
use Database\Factories\HabitMarkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable $period_date
 * @property HabitDirection $direction
 * @property int $xp_awarded
 * @property int $gold_awarded
 * @property CarbonImmutable $created_at
 */
#[Fillable(['period_date', 'direction', 'xp_awarded', 'gold_awarded', 'created_at'])]
final class HabitMark extends Model
{
    /** @use HasFactory<HabitMarkFactory> */
    use HasFactory;

    // A mark is never changed, only created or undone
    public $timestamps = false;

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    // What this mark gave; undoing it takes back exactly this
    public function reward(): Reward
    {
        return new Reward($this->xp_awarded, $this->gold_awarded);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_date' => 'immutable_date',
            'direction' => HabitDirection::class,
            'xp_awarded' => 'integer',
            'gold_awarded' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
