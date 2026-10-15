<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\WeekdaysCast;
use App\Enums\TaskDifficulty;
use App\Enums\TaskType;
use App\ValueObjects\Weekdays;
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
 * @property TaskDifficulty $difficulty
 * @property Weekdays $weekdays
 * @property int $position
 * @property-read User $user
 * @property-read TaskCompletion|null $latestCompletion
 */
#[Fillable(['title', 'type', 'difficulty', 'weekdays'])]
final class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SoftDeletes;

    // Mirrors the column defaults, so a just created task knows them without a reload
    protected $attributes = [
        'difficulty' => 'medium',
        'weekdays' => Weekdays::EVERY_DAY_MASK,
        'position' => 0,
    ];

    // Every way of creating a task puts it last, so no caller has to know about positions.
    // TaskController::store holds the user row lock around it, so parallel creations get different places
    protected static function booted(): void
    {
        self::creating(function (Task $task): void {
            if ($task->position === 0) {
                $task->position = (int) self::withTrashed()->where('user_id', $task->user_id)->max('position') + 1;
            }
        });
    }

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

    // A todo has no schedule; a daily is due only on its weekdays
    public function isScheduledOn(CarbonImmutable $day): bool
    {
        return $this->type === TaskType::Todo || $this->weekdays->includes($day);
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
            'difficulty' => TaskDifficulty::class,
            'weekdays' => WeekdaysCast::class,
            'position' => 'integer',
        ];
    }
}
