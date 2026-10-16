<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

// The timezone was changed too recently; the message is meant for the player
final class TimezoneChangeRefused extends DomainException
{
    public static function tooSoon(int $hours): self
    {
        return new self(__('profile.timezone_cooldown', ['hours' => $hours]));
    }
}
