<?php

declare(strict_types=1);

namespace App\ValueObjects;

final readonly class Reward
{
    public function __construct(public int $xp, public int $gold) {}

    public static function none(): self
    {
        return new self(0, 0);
    }

    // The change seen by the player when a reward is taken back
    public function negated(): self
    {
        return new self(-$this->xp, -$this->gold);
    }

    /**
     * @return array{xp: int, gold: int}
     */
    public function toArray(): array
    {
        return ['xp' => $this->xp, 'gold' => $this->gold];
    }
}
