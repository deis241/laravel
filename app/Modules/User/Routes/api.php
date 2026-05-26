<?php

use App\Modules\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('users')->group(function () {

    // ── CRUD ──────────────────────────────────────────────────────────────────
    Route::get('/',         [UserController::class, 'index']);
    Route::post('/',        [UserController::class, 'store']);
    Route::get('/{id}',     [UserController::class, 'show']);
    Route::put('/{id}',     [UserController::class, 'update']);
    Route::delete('/{id}',  [UserController::class, 'destroy']);

    // ── Gestion des rôles ─────────────────────────────────────────────────────
    Route::get('/{id}/roles',              [UserController::class, 'roles']);
    Route::post('/{id}/roles',             [UserController::class, 'assignRole']);
    Route::delete('/{id}/roles/{roleId}',  [UserController::class, 'removeRole']);
});
