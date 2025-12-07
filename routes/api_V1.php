<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\UserController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/users', [UserController::class, 'index'])->middleware('role:admin');
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store'])->middleware('role:admin');
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
});
