<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Mechanics tests run on this fixed balance, so tuning config/progression.php never rewrites them.
    // BalanceTargetTest and DemoSeederTest check the real numbers through useRealBalance()
    private const array REFERENCE_BALANCE = [
        'rewards' => ['xp' => 10, 'gold' => 5, 'daily_limit' => 10],
        'difficulty' => ['easy' => 50, 'medium' => 100, 'hard' => 200],
        'streak_bonus' => ['percent_per_day' => 10, 'max_percent' => 100],
        'levels' => ['base_xp' => 50, 'exponent' => 1.5],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['progression' => self::REFERENCE_BALANCE]);

        // Sanctum only starts a session for requests coming from a stateful SPA origin
        $this->withHeader('Origin', 'http://localhost:5173');
    }

    // The balance players get, for tests about the tuning itself and about the demo built on it
    protected function useRealBalance(): void
    {
        config(['progression' => require base_path('config/progression.php')]);
    }
}
