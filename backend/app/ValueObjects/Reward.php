<?php

declare(strict_types=1);

namespace App\ValueObjects;

final readonly class Reward
{
    // capped: the daily limit was reached, so this completion counts but gives nothing
    public function __construct(public int $xp, public int $gold, public bool $capped = false) {}

    public static function none(): self
    {
        return new self(0, 0);
    }

    public static function capped(): self
    {
        return new self(0, 0, capped: true);
    }

    // The change seen by the player when a reward is taken back
    public function negated(): self
    {
        return new self(-$this->xp, -$this->gold);
    }

    /**
     * @return array{xp: int, gold: int, capped: bool}
     */
    public function toArray(): array
    {
        return ['xp' => $this->xp, 'gold' => $this->gold, 'capped' => $this->capped];
    }
}
