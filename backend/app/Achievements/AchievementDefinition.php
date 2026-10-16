<?php

declare(strict_types=1);

namespace App\Achievements;

use App\Enums\AchievementMetric;

final readonly class AchievementDefinition
{
    // target: the value of the metric that earns it; unused for Catalog, whose target is the size of the shop.
    // rewardItemCode: the rare item given with it, if any
    public function __construct(
        public string $code,
        public string $title,
        public string $description,
        public AchievementMetric $metric,
        public int $target,
        public ?string $rewardItemCode = null,
    ) {}
}
