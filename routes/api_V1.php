<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\UserController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\YearController;
use App\Http\Controllers\Api\V1\InformativoController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/me', [UserController::class, 'me']);
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store'])->middleware('role:admin');
    Route::get('/users/notifications', [UserController::class, 'usersnotifications']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::get('/users/{id}/favorites', [UserController::class, 'favorites']);
    Route::get('/users/{id}/favorites/{favoriteId}', [UserController::class, 'showFavorite']);
    Route::delete('/users/{id}/favorites/{favoriteId}', [UserController::class, 'removeFavorite']);
    Route::delete('/users/{id}/favorites', [UserController::class, 'removeAllFavorites']);
    Route::get('/users/{id}/notifications', [UserController::class, 'notifications']);
    Route::get('/users/{id}/notifications/{notificationId}', [UserController::class, 'showNotification']);
    Route::post('/users/{userId}/notifications/{notificationId}/read', [UserController::class, 'markNotificationAsRead']);
    Route::post('/users/{userId}/notifications', [UserController::class, 'markAllNotificationsAsRead']);

    // Apply admin-only middleware to specific resource actions via associative map

    // Admin-only for store, update, destroy
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('departments', DepartmentController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('courses', CourseController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('years', YearController::class)->only(['store', 'update', 'destroy']);
    });

    // Public (or just auth) for index, show
    Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
    Route::apiResource('departments', DepartmentController::class)->only(['index', 'show']);
    Route::apiResource('courses', CourseController::class)->only(['index', 'show']);
    Route::apiResource('years', YearController::class)->only(['index', 'show']);
    Route::get('informativos/status', [InformativoController::class, 'statusOptions']);
    Route::get('informativos/files', [InformativoController::class, 'files']);
    Route::apiResource('informativos', InformativoController::class)->only(['index', 'show']);

    Route::group(['middleware' => ['role:admin|editor|revisor']],function () {
        Route::apiResource('informativos', InformativoController::class)->only(['store', 'update', 'destroy']);
        Route::post('informativos/{informativo}/schedule', [InformativoController::class, 'schedule']);

        Route::group(['middleware' => ['role:admin|revisor']],function () {
            Route::post('informativos/{informativo}/reject', [InformativoController::class, 'reject']);
            Route::post('informativos/{informativo}/approve', [InformativoController::class, 'approve']);
            Route::post('informativos/{informativo}/request-changes', [InformativoController::class, 'requestChanges']);
        });
    });

    //apenas se estiver publicado
    Route::post('informativos/{informativo}/favorite', [InformativoController::class, 'toggleFavorite']);
});
