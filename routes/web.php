<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SiteController;
use App\Models\GeneratedArticle;
use App\Models\Site;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return Inertia::render('Welcome', [
            'stats' => [
                'reviewCount' => GeneratedArticle::where('status', 'review')->count(),
                'publishedCount' => GeneratedArticle::where('status', 'published')->count(),
                'activeSites' => Site::where('is_active', true)->count(),
            ],
        ]);
    });

    Route::get('/review', [ReviewController::class, 'index'])->name('review.index');
    Route::post('/review/{article}/approve', [ReviewController::class, 'approve'])->name('review.approve');
    Route::post('/review/{article}/reject', [ReviewController::class, 'reject'])->name('review.reject');

    Route::get('/articles/published', [ArticleController::class, 'published'])->name('articles.published');
    Route::get('/sites', [SiteController::class, 'index'])->name('sites.index');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
