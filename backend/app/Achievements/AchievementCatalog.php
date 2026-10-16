<?php

declare(strict_types=1);

namespace App\Achievements;

use App\Enums\AchievementMetric;

// The definitions live in code; the database only remembers who earned what (user_achievements)
final class AchievementCatalog
{
    // code, title, description, metric, target, rare item. The order is the order of the achievements page
    private const array ACHIEVEMENTS = [
        ['first_step', 'Первый шаг', 'Выполнить первую задачу', AchievementMetric::ActiveDays, 1, null],
        ['streak_3', 'Разгон', 'Стрик 3 дня', AchievementMetric::LongestStreak, 3, null],
        ['streak_7', 'Неделя без пропусков', 'Стрик 7 дней', AchievementMetric::LongestStreak, 7, 'paladin_pants'],
        ['streak_30', 'Месяц дисциплины', 'Стрик 30 дней', AchievementMetric::LongestStreak, 30, 'amethyst_armor'],
        ['streak_100', 'Сотня', 'Стрик 100 дней', AchievementMetric::LongestStreak, 100, 'valkyrie_helmet'],
        ['active_10', 'Десять дней в деле', '10 дней с выполнениями', AchievementMetric::ActiveDays, 10, null],
        ['active_50', 'Полсотни', '50 дней с выполнениями', AchievementMetric::ActiveDays, 50, null],
        ['active_100', 'Сто дней в деле', '100 дней с выполнениями', AchievementMetric::ActiveDays, 100, 'hero_shield'],
        ['level_5', 'Опытный', 'Уровень 5', AchievementMetric::Level, 5, null],
        ['level_10', 'Ветеран', 'Уровень 10', AchievementMetric::Level, 10, 'frost_axe'],
        ['first_purchase', 'Первая покупка', 'Купить вещь в магазине', AchievementMetric::Purchases, 1, null],
        ['collector', 'Коллекционер', 'Купить весь магазин', AchievementMetric::Catalog, 0, null],
        ['habit_7', 'Хорошая привычка', 'Плюс привычке в 7 разных дней', AchievementMetric::HabitDays, 7, null],
    ];

    /**
     * @return list<AchievementDefinition>
     */
    public function all(): array
    {
        return array_map(
            fn (array $row): AchievementDefinition => new AchievementDefinition(...$row),
            self::ACHIEVEMENTS,
        );
    }
}
