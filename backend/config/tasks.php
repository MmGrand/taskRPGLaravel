<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | How many not deleted tasks one user may keep, and the maximum title
    | length. The title length also sizes the tasks.title column.
    |
    */

    'max_active_per_user' => (int) env('TASKS_MAX_ACTIVE_PER_USER', 100),

    'title_max_length' => 120,

];
