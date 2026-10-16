<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\WeekdaysCast;
use App\Enums\HabitDirection;
use App\Enums\TaskDifficulty;
use App\Enums\TaskType;
use App\ValueObjects\Weekdays;
use Carbon\CarbonImmutable;
use Closure;
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
 * @property-read int|null $habit_up_today
 * @property-read int|null $habit_down_today
 * @property-read int|null $habit_rewarded_today
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

    /**
     * @return HasMany<HabitMark, $this>
     */
    public function habitMarks(): HasMany
    {
        return $this->hasMany(HabitMark::class);
    }

    // A daily counts only for the given day; a todo stays done once completed; a habit is never done
    public function isCompletedOn(CarbonImmutable $day): bool
    {
        $completion = $this->latestCompletion;

        if ($completion === null || $this->type === TaskType::Habit) {
            return false;
        }

        // Compare calendar dates: period_date is midnight in UTC, $day is midnight in the user's zone
        return $this->type === TaskType::Todo
            || $completion->period_date->toDateString() === $day->toDateString();
    }

    // Only a daily has a schedule: it is due on its weekdays, a todo or a habit any day
    public function isScheduledOn(CarbonImmutable $day): bool
    {
        return $this->type !== TaskType::Daily || $this->weekdays->includes($day);
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
     * Today's plus, minus and rewarded marks as habit_up_today, habit_down_today and habit_rewarded_today.
     * Every task gets them, which is one query for the whole list; only a habit can have marks.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithHabitCounts(Builder $query, CarbonImmutable $day): void
    {
        $query->withCount(self::habitCounts($day));
    }

    // The same counts for a single task already loaded
    public function loadHabitCounts(CarbonImmutable $day): self
    {
        return $this->loadCount(self::habitCounts($day));
    }

    /**
     * @return array<string, Closure>
     */
    private static function habitCounts(CarbonImmutable $day): array
    {
        $date = $day->toDateString();

        return [
            'habitMarks as habit_up_today' => fn (Builder $marks) => $marks
                ->where('period_date', $date)->where('direction', HabitDirection::Up),
            'habitMarks as habit_down_today' => fn (Builder $marks) => $marks
                ->where('period_date', $date)->where('direction', HabitDirection::Down),
            'habitMarks as habit_rewarded_today' => fn (Builder $marks) => $marks
                ->where('period_date', $date)
                ->where(fn (Builder $rewarded) => $rewarded->where('xp_awarded', '>', 0)->orWhere('gold_awarded', '>', 0)),
        ];
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
