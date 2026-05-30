<?php

use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\MemberLoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\UpgradeAccountController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Public\JoinController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\Services\ServiceController;
use App\Http\Controllers\Services\ServiceSongController;
use App\Http\Controllers\Services\ShareController;
use App\Http\Controllers\Settings\BandSettingsController;
use App\Http\Controllers\Settings\IndexController as SettingsIndexController;
use App\Http\Controllers\Settings\MemberController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ScheduleTemplateController;
use App\Http\Controllers\Songs\SongController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public shared repertoire (no auth)
Route::get('/r/{token}', [ShareController::class, 'show'])->name('share.show');

// Public join via invite link (no auth required)
Route::get('/join/{token}', JoinController::class)->name('band.join');

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

// Registration
Route::get('/register', [RegisterController::class, 'show'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1')->name('register.store');

// Upgrade session-member to registered User (linked to their current band)
Route::get('/upgrade', [UpgradeAccountController::class, 'show'])->name('upgrade');
Route::post('/upgrade', [UpgradeAccountController::class, 'store'])->middleware('throttle:5,1')->name('upgrade.store');

// Password reset
Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');

// Email verification
Route::middleware('auth')->group(function () {
    Route::get('/verify-email', [VerifyEmailController::class, 'notice'])->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [VerifyEmailController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [VerifyEmailController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

// Protected
Route::middleware('band.access')->group(function () {
    Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('auth.logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Welcome overlay — admin dismisses it permanently
    Route::post('/welcome/dismiss', [WelcomeController::class, 'dismiss'])->name('welcome.dismiss');

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
    Route::put('/songs/{song}', [SongController::class, 'update'])->name('songs.update');
    Route::delete('/songs/{song}', [SongController::class, 'destroy'])->name('songs.destroy');

    // Settings — admin only
    Route::get('/settings', SettingsIndexController::class)->name('settings');

    Route::get('/settings/profile', [ProfileController::class, 'show'])->name('settings.profile');
    Route::put('/settings/profile', [ProfileController::class, 'update'])->name('settings.profile.update');
    Route::post('/settings/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('settings.profile.avatar');
    Route::put('/settings/profile/password', [ProfileController::class, 'updatePassword'])->name('settings.profile.password');

    Route::get('/settings/band', [BandSettingsController::class, 'show'])->name('settings.band');
    Route::put('/settings/band', [BandSettingsController::class, 'update'])->name('settings.band.update');
    Route::post('/settings/band/logo', [BandSettingsController::class, 'updateLogo'])->name('settings.band.logo');
    Route::post('/settings/band/regenerate-code', [BandSettingsController::class, 'regenerateCode'])->name('settings.band.regenerate-code');
    Route::post('/settings/band/regenerate-pin', [BandSettingsController::class, 'regeneratePin'])->name('settings.band.regenerate-pin');
    Route::post('/settings/band/regenerate-token', [BandSettingsController::class, 'regenerateToken'])->name('settings.band.regenerate-token');

    Route::get('/settings/members', [MemberController::class, 'index'])->name('settings.members');
    Route::post('/settings/members/{user}/promote', [MemberController::class, 'promote'])->name('settings.members.promote');
    Route::post('/settings/members/{user}/demote', [MemberController::class, 'demote'])->name('settings.members.demote');
    Route::delete('/settings/members/{user}', [MemberController::class, 'destroy'])->name('settings.members.destroy');
    Route::delete('/settings/visitors', [MemberController::class, 'resetVisitors'])->name('settings.visitors.reset');

    Route::get('/settings/schedule-templates', [ScheduleTemplateController::class, 'index'])->name('settings.templates');
    Route::post('/settings/schedule-templates', [ScheduleTemplateController::class, 'store'])->name('settings.templates.store');
    Route::put('/settings/schedule-templates/{scheduleTemplate}', [ScheduleTemplateController::class, 'update'])->name('settings.templates.update');
    Route::delete('/settings/schedule-templates/{scheduleTemplate}', [ScheduleTemplateController::class, 'destroy'])->name('settings.templates.destroy');
});
