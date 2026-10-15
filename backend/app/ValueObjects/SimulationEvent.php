<?php

declare(strict_types=1);

namespace App\ValueObjects;

// One line of a simulated playthrough: a level reached (with what it unlocked) or an item bought
final readonly class SimulationEvent
{
    public const string LEVEL = 'level';

    public const string PURCHASE = 'purchase';

    public function __construct(public int $day, public string $kind, public string $text) {}
}
