<?php

use App\Presentation\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::middleware(['auth:sanctum'])->group(function () {
        // Rutas que requieren autenticación
        
        Route::middleware(['role:admin'])->group(function () {
            // Rutas solo para administradores
        });
    
        Route::middleware(['permission:create users'])->group(function () {
            // Rutas que requieren permiso específico
        });
    });
    
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});