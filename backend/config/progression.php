<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Rewards
    |--------------------------------------------------------------------------
    |
    | XP and gold one completed task gives before the streak bonus. Only the
    | first daily_limit completions of a user's day are rewarded; the rest
    | still count as done and keep the streak, but give nothing.
    |
    */

    'rewards' => [
        'xp' => 10,
        'gold' => 5,
        'daily_limit' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Difficulty
    |--------------------------------------------------------------------------
    |
    | Percent of the base reward a task of each difficulty gives. The streak
    | bonus applies on top: a hard task on streak day 11 gives x2 * x2.
    |
    */

    'difficulty' => [
        'easy' => 50,
        'medium' => 100,
        'hard' => 200,
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
