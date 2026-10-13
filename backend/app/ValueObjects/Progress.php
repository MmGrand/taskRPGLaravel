<?php

declare(strict_types=1);

namespace App\ValueObjects;

final readonly class Progress
{
    public function __construct(
        public int $level,
        public int $xpTotal,
        public int $xpInLevel,
        public int $xpToNextLevel,
    ) {}
}
