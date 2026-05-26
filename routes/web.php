<?php

use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\MemberLoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Services\ServiceController;
use App\Http\Controllers\Services\ServiceSongController;
use App\Http\Controllers\Services\ShareController;
use App\Http\Controllers\Settings\ScheduleTemplateController;
use App\Http\Controllers\Songs\SongController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public shared repertoire (no auth)
Route::get('/r/{token}', [ShareController::class, 'show'])->name('share.show');

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'appName' => config('app.name'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Auth
Route::get('/login', [AdminLoginController::class, 'show'])->name('auth.login');
Route::post('/login', [AdminLoginController::class, 'store'])->name('auth.admin.login');
Route::post('/join', [MemberLoginController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('auth.join');

// Protected
Route::middleware('band.access')->group(function () {
    Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('auth.logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Services
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::get('/services/create', [ServiceController::class, 'create'])->name('services.create');
    Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
    Route::get('/services/{service}', [ServiceController::class, 'show'])->name('services.show');
    Route::get('/services/{service}/edit', [ServiceController::class, 'edit'])->name('services.edit');
    Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
    Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
    Route::post('/services/{service}/duplicate', [ServiceController::class, 'duplicate'])->name('services.duplicate');
    Route::post('/services/{service}/share', [ShareController::class, 'store'])->name('services.share');

    // Service songs
    Route::post('/services/{service}/songs/reorder', [ServiceSongController::class, 'reorder'])->name('service-songs.reorder');
    Route::post('/services/{service}/songs', [ServiceSongController::class, 'store'])->name('service-songs.store');
    Route::delete('/services/{service}/songs/{serviceSong}', [ServiceSongController::class, 'destroy'])->name('service-songs.destroy');

    // Songs library
    Route::get('/songs', [SongController::class, 'index'])->name('songs.index');
    Route::post('/songs', [SongController::class, 'store'])->name('songs.store');
    Route::delete('/songs/{song}', [SongController::class, 'destroy'])->name('songs.destroy');

    // Settings — admin only
    Route::get('/settings/schedule-templates', [ScheduleTemplateController::class, 'index'])->name('settings.templates');
    Route::post('/settings/schedule-templates', [ScheduleTemplateController::class, 'store'])->name('settings.templates.store');
    Route::put('/settings/schedule-templates/{scheduleTemplate}', [ScheduleTemplateController::class, 'update'])->name('settings.templates.update');
    Route::delete('/settings/schedule-templates/{scheduleTemplate}', [ScheduleTemplateController::class, 'destroy'])->name('settings.templates.destroy');
});
