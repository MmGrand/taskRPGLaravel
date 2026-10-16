<?php

declare(strict_types=1);

namespace App\Enums;

// What an achievement measures. Each grows over days, so none can be earned in one sitting:
// the count of completed tasks is left out on purpose, a hundred todos are made and ticked in a minute
enum AchievementMetric: string
{
    // Days with a completion or a habit plus, in a row or not
    case ActiveDays = 'active_days';
    // The longest streak ever, so a broken streak keeps its record
    case LongestStreak = 'longest_streak';
    case Level = 'level';
    // Items bought in the shop; the starter kit and rare items are not bought
    case Purchases = 'purchases';
    // Every item of the shop bought; the target is the size of the shop
    case Catalog = 'catalog';
    // Days with a habit plus
    case HabitDays = 'habit_days';
}
