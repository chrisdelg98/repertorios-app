<?php

use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\MemberLoginController;
use App\Http\Controllers\DashboardController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'appName' => config('app.name'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Auth routes
Route::get('/login', [AdminLoginController::class, 'show'])->name('auth.login');
Route::post('/login', [AdminLoginController::class, 'store'])->name('auth.admin.login');
Route::post('/join', [MemberLoginController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('auth.join');

// Protected routes
Route::middleware('band.access')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('auth.logout');
});
