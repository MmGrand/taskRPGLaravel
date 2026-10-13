<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TaskCompletionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable $period_date
 * @property CarbonImmutable $completed_at
 */
#[Fillable(['period_date', 'completed_at'])]
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
        ];
    }
}
