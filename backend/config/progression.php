<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Rewards
    |--------------------------------------------------------------------------
    |
    | XP and gold one completed task gives before the streak bonus.
    |
    */

    'rewards' => [
        'xp' => 10,
        'gold' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Streak bonus
    |--------------------------------------------------------------------------
    |
    | Every streak day after the first adds this many percent to a reward,
    | up to the maximum: with 10 and 100 day 2 gives x1.1, day 11 gives x2.
    |
    */

    'streak_bonus' => [
        'percent_per_day' => 10,
        'max_percent' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Level curve
    |--------------------------------------------------------------------------
    |
    | XP needed to leave a level: round(base_xp * level ^ exponent).
    | An exponent above 1 makes every next level harder.
    |
    */

    'levels' => [
        'base_xp' => 50,
        'exponent' => 1.5,
    ],

];
