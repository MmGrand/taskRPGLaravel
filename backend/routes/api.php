<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\CharacterController;
use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\ShopVisitController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\TaskCompletionController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::post('/register', RegisterController::class);
Route::post('/login', LoginController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', LogoutController::class);
    Route::get('/user', CurrentUserController::class);
    Route::get('/character', [CharacterController::class, 'show']);
    Route::post('/character', [CharacterController::class, 'store']);
    Route::get('/character/inventory', InventoryController::class);
    Route::put('/character/equipment/{slot}', [EquipmentController::class, 'update']);
    Route::delete('/character/equipment/{slot}', [EquipmentController::class, 'destroy']);
    Route::put('/character/shop-seen', ShopVisitController::class);

    Route::get('/stats', StatsController::class);

    Route::get('/shop', ShopController::class);
    Route::post('/shop/purchases', PurchaseController::class);

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    // Above the {task} routes, so "order" is never read as a task id
    Route::put('/tasks/order', TaskOrderController::class);
    Route::patch('/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
    Route::put('/tasks/{task}/completion', [TaskCompletionController::class, 'store']);
    Route::delete('/tasks/{task}/completion', [TaskCompletionController::class, 'destroy']);
});
