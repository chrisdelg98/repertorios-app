<?php

use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\MemberLoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\UpgradeAccountController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\BandController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\Public\JoinController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\Services\ServiceController;
use App\Http\Controllers\Services\ServiceSongController;
use App\Http\Controllers\Services\ServiceAssignmentController;
use App\Http\Controllers\Services\ShareController;
use App\Http\Controllers\Settings\AudioLibraryController;
use App\Http\Controllers\Settings\BandSettingsController;
use App\Http\Controllers\Settings\IndexController as SettingsIndexController;
use App\Http\Controllers\Settings\MemberController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ScheduleTemplateController;
use App\Http\Controllers\Songs\SongAudioController;
use App\Http\Controllers\Songs\SongController;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public shared repertoire (no auth)
Route::get('/r/{token}', [ShareController::class, 'show'])->name('share.show');

// Public join via invite link (no auth required)
Route::get('/join/{token}', JoinController::class)->name('band.join');

// The landing page is for people who are not in yet. Anyone with a session —
// a registered user or a guest who came through a PIN or an invite link — goes
// straight to their dashboard. This is also the PWA's start_url, so the
// installed app opens on the dashboard instead of the sales pitch.
Route::get('/', function (Request $request) {
    if (Auth::check() || $request->session()->has('band_id')) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome', [
        'appName' => config('app.name'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('home');

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

// Logout sits outside band.access on purpose: a user who belongs to no band is
// bounced to bands.create by that middleware, and would not be able to log out.
Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('auth.logout');

// Web Push: a browser registers and unregisters itself here. Registered users
// only — a guest has no account to attach a device to.
Route::middleware('auth')->group(function () {
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/push/test', [PushSubscriptionController::class, 'test'])
        ->middleware('throttle:6,1')
        ->name('push.test');
    Route::delete('/push/subscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
    Route::delete('/push/devices/{pushSubscription}', [PushSubscriptionController::class, 'destroyDevice'])->name('push.devices.destroy');
});

// Multi-band: switching and starting an extra band. Registered users only —
// these sit outside band.access because they are what you reach when you have
// no active band yet.
Route::middleware('auth')->group(function () {
    Route::get('/bands/create', [BandController::class, 'create'])->name('bands.create');
    Route::post('/bands', [BandController::class, 'store'])->middleware('throttle:10,1')->name('bands.store');
    Route::post('/bands/{band}/switch', [BandController::class, 'switch'])->name('bands.switch');
});

// Protected
Route::middleware('band.access')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Welcome overlay — admin dismisses it permanently
    Route::post('/welcome/dismiss', [WelcomeController::class, 'dismiss'])->name('welcome.dismiss');

    // Calendar: everything the band has scheduled. Services are read here but
    // written under Services; this only creates rehearsals, meetings and the like.
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
    Route::post('/calendar', [CalendarController::class, 'store'])->name('calendar.store');
    Route::put('/calendar/{entry}', [CalendarController::class, 'update'])->name('calendar.update');
    Route::delete('/calendar/{entry}', [CalendarController::class, 'destroy'])->name('calendar.destroy');

    // Services
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::get('/services/create', [ServiceController::class, 'create'])->name('services.create');
    Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
    Route::get('/services/{service}', [ServiceController::class, 'show'])->name('services.show');
    Route::get('/services/{service}/edit', [ServiceController::class, 'edit'])->name('services.edit');
    Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
    Route::patch('/services/{service}/color', [ServiceController::class, 'updateColor'])->name('services.color');
    Route::post('/services/{service}/notify', [ServiceController::class, 'notifyTeam'])
        ->middleware('throttle:10,1')
        ->name('services.notify');
    Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
    Route::post('/services/{service}/duplicate', [ServiceController::class, 'duplicate'])->name('services.duplicate');
    Route::post('/services/{service}/share', [ShareController::class, 'store'])->name('services.share');
    Route::put('/shared-links/{sharedLink}', [ShareController::class, 'update'])->name('shared-links.update');

    // Service songs
    Route::post('/services/{service}/songs/reorder', [ServiceSongController::class, 'reorder'])->name('service-songs.reorder');
    Route::post('/services/{service}/songs', [ServiceSongController::class, 'store'])->name('service-songs.store');
    Route::patch('/services/{service}/songs/{serviceSong}', [ServiceSongController::class, 'update'])->name('service-songs.update');
    Route::delete('/services/{service}/songs/{serviceSong}', [ServiceSongController::class, 'destroy'])->name('service-songs.destroy');

    // Service assignments
    Route::post('/services/{service}/assignments', [ServiceAssignmentController::class, 'store'])->name('service-assignments.store');
    Route::patch('/assignments/{assignment}', [ServiceAssignmentController::class, 'update'])->name('service-assignments.update');
    Route::delete('/assignments/{assignment}', [ServiceAssignmentController::class, 'destroy'])->name('service-assignments.destroy');

    // Rehearsal audio. The file itself never touches this server: the browser
    // uploads it straight to R2 with a URL signed here.
    Route::post('/song-versions/{songVersion}/audio/presign', [SongAudioController::class, 'presign'])
        ->middleware('throttle:30,1')
        ->name('song-audio.presign');
    Route::put('/song-versions/{songVersion}/audio', [SongAudioController::class, 'attach'])->name('song-audio.attach');
    Route::delete('/song-versions/{songVersion}/audio', [SongAudioController::class, 'destroy'])->name('song-audio.destroy');
    Route::get('/song-versions/{songVersion}/audio/url', [SongAudioController::class, 'play'])->name('song-audio.url');

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
    Route::put('/settings/members/{user}/roles', [MemberController::class, 'assignRoles'])->name('settings.members.roles');
    Route::delete('/settings/members/{user}', [MemberController::class, 'destroy'])->name('settings.members.destroy');
    Route::delete('/settings/visitors', [MemberController::class, 'resetVisitors'])->name('settings.visitors.reset');

    Route::get('/settings/audio', [AudioLibraryController::class, 'index'])->name('settings.audio');
    Route::delete('/settings/audio/{songVersion}', [AudioLibraryController::class, 'destroy'])->name('settings.audio.destroy');

    Route::get('/settings/schedule-templates', [ScheduleTemplateController::class, 'index'])->name('settings.templates');
    Route::post('/settings/schedule-templates', [ScheduleTemplateController::class, 'store'])->name('settings.templates.store');
    Route::put('/settings/schedule-templates/{scheduleTemplate}', [ScheduleTemplateController::class, 'update'])->name('settings.templates.update');
    Route::delete('/settings/schedule-templates/{scheduleTemplate}', [ScheduleTemplateController::class, 'destroy'])->name('settings.templates.destroy');
});
