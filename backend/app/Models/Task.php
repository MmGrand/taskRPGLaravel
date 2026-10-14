<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskType;
use Carbon\CarbonImmutable;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property TaskType $type
 * @property-read User $user
 * @property-read TaskCompletion|null $latestCompletion
 */
#[Fillable(['title', 'type'])]
final class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TaskCompletion, $this>
     */
    public function completions(): HasMany
    {
        return $this->hasMany(TaskCompletion::class);
    }

    /**
     * @return HasOne<TaskCompletion, $this>
     */
    public function latestCompletion(): HasOne
    {
        return $this->hasOne(TaskCompletion::class)->latestOfMany('period_date');
    }

    // A daily counts only for the given day; a todo stays done once completed
    public function isCompletedOn(CarbonImmutable $day): bool
    {
        $completion = $this->latestCompletion;

        if ($completion === null) {
            return false;
        }

        // Compare calendar dates: period_date is midnight in UTC, $day is midnight in the user's zone
        return $this->type === TaskType::Todo
            || $completion->period_date->toDateString() === $day->toDateString();
    }

    // Whether today's mark gave something, so undoing or deleting it would take a reward back
    public function isRewardedOn(CarbonImmutable $day): bool
    {
        $completion = $this->latestCompletion;

        return $completion !== null
            && $completion->period_date->toDateString() === $day->toDateString()
            && ($completion->xp_awarded > 0 || $completion->gold_awarded > 0);
    }

    /**
     * Tasks the user still sees on the given day: todos completed on an earlier day drop off the list.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActiveOn(Builder $query, CarbonImmutable $day): void
    {
        $query->where(fn (Builder $tasks) => $tasks
            ->where('type', TaskType::Daily)
            ->orWhereDoesntHave('completions', fn (Builder $completions) => $completions
                ->where('period_date', '<', $day->toDateString())));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
        ];
    }
}
