<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskDifficulty: string
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    // Share of the base reward in percent, from config/progression.php
    public function rewardPercent(): int
    {
        return config()->integer("progression.difficulty.{$this->value}");
    }
}
