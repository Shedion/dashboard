<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Reminders\ReminderCategoryController;
use App\Http\Controllers\Reminders\ReminderController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn() => response()->json(['status' => 'ok']));

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:register');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('reminder-categories', ReminderCategoryController::class)
        ->except('show');

    Route::get('/reminders/due', [ReminderController::class, 'due']);
    Route::patch('/reminders/{reminder}/complete', [ReminderController::class, 'complete']);
    Route::patch('/reminders/{reminder}/uncomplete', [ReminderController::class, 'uncomplete']);
    Route::patch('/reminders/{reminder}/shown', [ReminderController::class, 'markShown']);
    Route::apiResource('reminders', ReminderController::class)
        ->except('show');
});
