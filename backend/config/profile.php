<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | The user's day, daily resets and rewards follow the timezone. Changing it
    | moves "today", so it may change at most once per this many hours: a jump
    | to a zone where it is already tomorrow cannot be repeated to earn days.
    |
    */

    'timezone_change_cooldown_hours' => (int) env('PROFILE_TIMEZONE_COOLDOWN_HOURS', 24),

];
