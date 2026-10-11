<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\CharacterController;
use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::post('/register', RegisterController::class);
Route::post('/login', LoginController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', LogoutController::class);
    Route::get('/user', CurrentUserController::class);
    Route::get('/character', [CharacterController::class, 'show']);
    Route::post('/character', [CharacterController::class, 'store']);
});
