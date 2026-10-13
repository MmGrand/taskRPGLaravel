<?php

declare(strict_types=1);

namespace App\Models;

use App\ValueObjects\Reward;
use Carbon\CarbonImmutable;
use Database\Factories\TaskCompletionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable $period_date
 * @property CarbonImmutable $completed_at
 * @property int $xp_awarded
 * @property int $gold_awarded
 */
#[Fillable(['period_date', 'completed_at', 'xp_awarded', 'gold_awarded'])]
final class TaskCompletion extends Model
{
    /** @use HasFactory<TaskCompletionFactory> */
    use HasFactory;

    // completed_at is the creation moment; an update timestamp means nothing here
    public $timestamps = false;

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    // What this completion gave; undoing it takes back exactly this
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
            'completed_at' => 'immutable_datetime',
            'xp_awarded' => 'integer',
            'gold_awarded' => 'integer',
        ];
    }
}
