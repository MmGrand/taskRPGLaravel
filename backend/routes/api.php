<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\CharacterController;
use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\TaskCompletionController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::post('/register', RegisterController::class);
Route::post('/login', LoginController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', LogoutController::class);
    Route::get('/user', CurrentUserController::class);
    Route::get('/character', [CharacterController::class, 'show']);
    Route::post('/character', [CharacterController::class, 'store']);

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::patch('/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
    Route::put('/tasks/{task}/completion', [TaskCompletionController::class, 'store']);
    Route::delete('/tasks/{task}/completion', [TaskCompletionController::class, 'destroy']);
});
