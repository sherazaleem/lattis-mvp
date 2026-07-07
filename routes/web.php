<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return Inertia::render('Welcome');
    });

    Route::get('/review', [ReviewController::class, 'index'])->name('review.index');
    Route::post('/review/{article}/approve', [ReviewController::class, 'approve'])->name('review.approve');
    Route::post('/review/{article}/reject', [ReviewController::class, 'reject'])->name('review.reject');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
