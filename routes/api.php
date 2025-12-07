<?php

use Illuminate\Support\Facades\Route;

Route::get('/status', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true);
    $name = $composer['name'] ?? 'unknown';
    $version = $composer['version'] ?? 'unknown';
    return response()->json([
        'message' => 'API is working',
        'app' => $name,
        'version' => $version,
    ]);
});


Route::post('/login', function () {
    return response()->json(['message' => 'Login endpoint']);
});
Route::post('/logout', function () {
    return response()->json(['message' => 'Logout endpoint']);
})->middleware('auth:sanctum');
