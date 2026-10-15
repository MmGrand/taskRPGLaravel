<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

// The player may not complete this task right now; the message is meant for the player
final class CompletionRefused extends DomainException
{
    public static function notScheduled(): self
    {
        return new self(__('tasks.not_scheduled'));
    }
}
