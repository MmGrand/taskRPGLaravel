<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\TimezoneChangeRefused;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TimezoneService
{
    private const int MINUTES_PER_HOUR = 60;

    // Moves the user's "today" to another zone. Setting the same zone changes nothing and keeps the cooldown.
    // Returns the saved row. Throws TimezoneChangeRefused while the last change is within the cooldown
    public function change(User $user, string $timezone): User
    {
        return DB::transaction(function () use ($user, $timezone): User {
            // Parallel changes would both pass the cooldown check otherwise
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($locked->timezone === $timezone) {
                return $locked;
            }

            $availableAt = $locked->timezone_changed_at
                ?->addHours(config()->integer('profile.timezone_change_cooldown_hours'));

            if ($availableAt !== null && $availableAt->isFuture()) {
                throw TimezoneChangeRefused::tooSoon((int) ceil(now()->diffInMinutes($availableAt) / self::MINUTES_PER_HOUR));
            }

            $locked->forceFill(['timezone' => $timezone, 'timezone_changed_at' => now()])->save();

            return $locked;
        });
    }
}
