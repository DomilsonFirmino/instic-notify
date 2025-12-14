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
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::get('/users/{id}/favorites', [UserController::class, 'favorites']);
    Route::get('/users/{id}/notifications', [UserController::class, 'notifications']);
    Route::post('/users/{userId}/notifications/{notificationId}/read', [UserController::class, 'markNotificationAsRead']);
    Route::post('/users/notifications/mark-all-read', [UserController::class, 'markAllNotificationsAsRead']);

    // Apply admin-only middleware to specific resource actions via associative map
    Route::apiResource('categories', CategoryController::class)
        ->middleware([
            'store' => 'role:admin',
            'update' => 'role:admin',
            'destroy' => 'role:admin',
        ]);
    Route::apiResource('departments', DepartmentController::class)
        ->middleware([
            'store' => 'role:admin',
            'update' => 'role:admin',
            'destroy' => 'role:admin',
        ]);
    Route::apiResource('courses', CourseController::class)
        ->middleware([
            'store' => 'role:admin',
            'update' => 'role:admin',
            'destroy' => 'role:admin',
        ]);
    Route::apiResource('years', YearController::class)
        ->middleware([
            'store' => 'role:admin',
            'update' => 'role:admin',
            'destroy' => 'role:admin',
        ]);

    Route::apiResource('informativos', InformativoController::class);
    Route::post('informativos/{informativo}/schedule', [InformativoController::class, 'schedule']);
    Route::post('informativos/{informativo}/reject', [InformativoController::class, 'reject']);
    Route::post('informativos/{informativo}/approve', [InformativoController::class, 'approve']);
    Route::post('informativos/{informativo}/request-changes', [InformativoController::class, 'requestChanges']);
    Route::post('informativos/{informativo}/favorite', [InformativoController::class, 'toggleFavorite']);
});
