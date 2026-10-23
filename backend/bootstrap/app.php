<?php

declare(strict_types=1);

use App\Exceptions\CompletionRefused;
use App\Exceptions\HabitMarkRefused;
use App\Exceptions\TimezoneChangeRefused;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    // An API for the SPA only: no web pages besides the API docs, which Scramble registers itself
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Refusals meant for the player are their mistakes, not crashes, wherever they are thrown
        $exceptions->map(
            CompletionRefused::class,
            fn (CompletionRefused $refused) => ValidationException::withMessages(['task' => $refused->getMessage()]),
        );
        $exceptions->map(
            HabitMarkRefused::class,
            fn (HabitMarkRefused $refused) => ValidationException::withMessages(['task' => $refused->getMessage()]),
        );
        $exceptions->map(
            TimezoneChangeRefused::class,
            fn (TimezoneChangeRefused $refused) => ValidationException::withMessages(['timezone' => $refused->getMessage()]),
        );
    })->create();
