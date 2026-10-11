<?php

declare(strict_types=1);

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the Laravel application. Opt into RefreshDatabase per
| test file once tests start touching the schema.
|
*/

pest()->extend(TestCase::class)
    ->in('Feature');
