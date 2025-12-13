<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\UserController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\YearController;
use App\Http\Controllers\Api\V1\InformativoController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/users', [UserController::class, 'index'])->middleware('role:admin');
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store'])->middleware('role:admin');
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::get('/users/{id}/favorites', [UserController::class, 'favorites']);
    Route::get('/users/{id}/notifications', [UserController::class, 'notifications']);
    Route::post('/users/{userId}/notifications/{notificationId}/read', [UserController::class, 'markNotificationAsRead']);
    Route::post('/users/notifications/mark-all-read', [UserController::class, 'markAllNotificationsAsRead']);
    Route::get('/users/me', [UserController::class, 'me']);

    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('courses', CourseController::class);
    Route::apiResource('years', YearController::class);
    Route::apiResource('informativos', InformativoController::class);
    Route::post('informativos/{informativo}/publish', [InformativoController::class, 'publish']);
    Route::post('informativos/{informativo}/unpublish', [InformativoController::class, 'unpublish']);
    Route::post('informativos/{informativo}/reject', [InformativoController::class, 'reject']);
    Route::post('informativos/{informativo}/favorite', [InformativoController::class, 'toggleFavorite']);
});
