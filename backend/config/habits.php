<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Rewards
    |--------------------------------------------------------------------------
    |
    | A habit plus is rewarded like a completed task, within the shared daily
    | limit of config/progression.php. One habit pays for at most this many
    | pluses a day, so tapping one button cannot take the whole daily limit.
    | Later pluses still count and keep the streak, but give nothing.
    |
    */

    'rewarded_per_day' => 3,

    /*
    |--------------------------------------------------------------------------
    | Marks
    |--------------------------------------------------------------------------
    |
    | Pluses and minuses one habit may take a day. Far above any honest use;
    | it only keeps a script from filling the table.
    |
    */

    'max_marks_per_day' => 50,

];
