<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Achievements\AchievementDefinition;
use App\Enums\AchievementMetric;

// What a player has reached on every metric, read once for all achievements
final readonly class AchievementMetrics
{
    /**
     * @param  array<string, int>  $values  keyed by AchievementMetric values
     * @param  int  $catalogSize  items that can be bought in the shop
     */
    public function __construct(private array $values, public int $catalogSize) {}

    public function value(AchievementMetric $metric): int
    {
        return $this->values[$metric->value] ?? 0;
    }

    // The whole shop for the collector, the definition's own target for the rest
    public function targetOf(AchievementDefinition $definition): int
    {
        return $definition->metric === AchievementMetric::Catalog ? $this->catalogSize : $definition->target;
    }

    // An empty shop has nothing to collect, so the collector cannot be earned for free
    public function reached(AchievementDefinition $definition): bool
    {
        $target = $this->targetOf($definition);

        return $target > 0 && $this->value($definition->metric) >= $target;
    }
}
