<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

final class UserCalendar
{
    // Start of the current day on the user's wall clock: the single source of "today" for tasks
    public function today(User $user): CarbonImmutable
    {
        return now($user->timezone)->toImmutable()->startOfDay();
    }
}
