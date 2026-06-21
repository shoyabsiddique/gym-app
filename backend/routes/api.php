<?php

use App\Http\Controllers\AiJobController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DishController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\InsightsController;
use App\Http\Controllers\MealLogController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeightController;
use App\Http\Middleware\RequireAdmin;
use Illuminate\Support\Facades\Route;

// Public auth routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
    Route::post('refresh',  [AuthController::class, 'refresh']);
});

// Protected routes
Route::middleware('auth:api')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me',      [AuthController::class, 'me']);

    // User profile
    Route::get('users/profile', [UserController::class, 'profile']);
    Route::put('users/profile', [UserController::class, 'updateProfile']);

    // Dishes (read for all, write for admin)
    Route::get('dishes',        [DishController::class, 'index']);
    Route::get('dishes/{dish}', [DishController::class, 'show']);
    Route::middleware(RequireAdmin::class)->group(function () {
        Route::post('dishes',          [DishController::class, 'store']);
        Route::put('dishes/{dish}',    [DishController::class, 'update']);
        Route::delete('dishes/{dish}', [DishController::class, 'destroy']);
    });

    // Menus (read for all, write for admin)
    Route::get('menus', [MenuController::class, 'index']);
    Route::middleware(RequireAdmin::class)->group(function () {
        Route::post('menus',                  [MenuController::class, 'store']);
        Route::post('menus/import',           [MenuController::class, 'import']);
        Route::post('menus/upload-images',    [MenuController::class, 'uploadImages']);
        Route::put('menus/{menu}',            [MenuController::class, 'update']);
        Route::delete('menus/{menu}',         [MenuController::class, 'destroy']);
    });

    // Recommendations
    Route::get('recommendations/swap',  [RecommendationController::class, 'swap']);
    Route::post('recommendations/swap', [RecommendationController::class, 'confirmSwap']);
    Route::get('recommendations/today', [RecommendationController::class, 'today']);
    Route::post('recommendations/generate', [RecommendationController::class, 'generate']);
    Route::get('recommendations/week',  [RecommendationController::class, 'week']);

    // Meal logging
    Route::get('meal-logs',              [MealLogController::class, 'index']);
    Route::post('meal-logs',             [MealLogController::class, 'store']);
    Route::delete('meal-logs/{mealLog}', [MealLogController::class, 'destroy']);

    // Insights
    Route::get('insights/weekly', [InsightsController::class, 'weekly']);

    // Weight tracking
    Route::post('weight',         [WeightController::class, 'store']);
    Route::get('weight/history',  [WeightController::class, 'history']);

    // AI Jobs (admin only)
    Route::middleware(RequireAdmin::class)->group(function () {
        Route::get('ai-jobs',              [AiJobController::class, 'index']);
        Route::post('ai-jobs/{aiJob}/retry', [AiJobController::class, 'retry']);
    });
});
