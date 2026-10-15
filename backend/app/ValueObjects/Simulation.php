<?php

declare(strict_types=1);

namespace App\ValueObjects;

final readonly class Simulation
{
    /**
     * @param  list<SimulationEvent>  $events  in the order they happened
     * @param  array<int, int>  $levelDays  level => the day it was reached
     * @param  int|null  $catalogDay  the day the last item was bought, null when the days ran out first
     */
    public function __construct(
        public array $events,
        public array $levelDays,
        public ?int $catalogDay,
        public int $finalLevel,
    ) {}
}
