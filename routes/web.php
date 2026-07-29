<?php

use App\Http\Controllers\ActivityAttendanceController;
use App\Http\Controllers\AttendanceResponseController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ActivityDocumentationController;
use App\Http\Controllers\ActivityInvitationController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ActivityMinutesController;
use App\Http\Controllers\ActivityPublicationController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\LegacyFormRedirectController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicAttendanceController;
use App\Http\Controllers\PublicFormController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});

Route::post('/logout', LogoutController::class)
    ->middleware('auth')
    ->name('logout');

// Public attendance routes — must stay before legacy catch-all /{slug}.
Route::prefix('absensi/{public_token}')
    ->name('attendance.public.')
    ->group(function (): void {
        Route::get('/berhasil', [PublicAttendanceController::class, 'success'])
            ->name('success');

        Route::get('/', [PublicAttendanceController::class, 'show'])
            ->name('show');

        Route::post('/', [PublicAttendanceController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('store');
    });

// Short public attendance aliases — keep long /absensi/{public_token} alive.
Route::prefix('a/{short_code}')
    ->where(['short_code' => '[A-HJ-NP-Za-hj-np-z2-9]{6,12}'])
    ->name('attendance.short.')
    ->group(function (): void {
        Route::get('/', [PublicAttendanceController::class, 'showByShortCode'])
            ->name('show');

        Route::post('/', [PublicAttendanceController::class, 'storeByShortCode'])
            ->middleware('throttle:10,1')
            ->name('store');
    });

Route::middleware(['auth', 'active', 'custom.app'])->group(function () {
    Route::get('/ganti-password', [ChangePasswordController::class, 'edit'])->name('password.change');
    Route::put('/ganti-password', [ChangePasswordController::class, 'update'])->name('password.update');
});

Route::middleware(['auth', 'active', 'custom.app', 'password.changed'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/kalender', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/kalender/events', [CalendarController::class, 'events'])->name('calendar.events');

    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/files/{type}/{id}', [FileController::class, 'show'])
        ->where('type', 'invitation|minutes|documentation|signature')
        ->name('files.show');

    Route::prefix('kegiatan')->name('activities.')->group(function () {
        Route::get('/', [ActivityController::class, 'index'])->name('index');
        Route::get('/create', [ActivityController::class, 'create'])->name('create');
        Route::post('/', [ActivityController::class, 'store'])->name('store');

        // Document & attendance routes (specific paths) before generic {activity} routes.
        Route::post('/{activity}/surat-undangan', [ActivityInvitationController::class, 'store'])->name('surat-undangan.store');
        Route::get('/{activity}/surat-undangan/view', [ActivityInvitationController::class, 'view'])->name('surat-undangan.view');
        Route::get('/{activity}/surat-undangan/download', [ActivityInvitationController::class, 'download'])->name('surat-undangan.download');
        Route::delete('/{activity}/surat-undangan', [ActivityInvitationController::class, 'destroy'])->name('surat-undangan.destroy');

        Route::post('/{activity}/notulensi', [ActivityMinutesController::class, 'store'])->name('notulensi.store');
        Route::get('/{activity}/notulensi/view', [ActivityMinutesController::class, 'view'])->name('notulensi.view');
        Route::get('/{activity}/notulensi/download', [ActivityMinutesController::class, 'download'])->name('notulensi.download');
        Route::delete('/{activity}/notulensi', [ActivityMinutesController::class, 'destroy'])->name('notulensi.destroy');

        Route::post('/{activity}/dokumentasi', [ActivityDocumentationController::class, 'store'])->name('dokumentasi.store');
        Route::put('/{activity}/dokumentasi/{documentation}', [ActivityDocumentationController::class, 'update'])->name('dokumentasi.update');
        Route::delete('/{activity}/dokumentasi/{documentation}', [ActivityDocumentationController::class, 'destroy'])->name('dokumentasi.destroy');
        Route::get('/{activity}/dokumentasi/{documentation}/view', [ActivityDocumentationController::class, 'view'])->name('dokumentasi.view');
        Route::get('/{activity}/dokumentasi/{documentation}/download', [ActivityDocumentationController::class, 'download'])->name('dokumentasi.download');
        Route::get('/{activity}/dokumentasi/{documentation}/thumbnail', [ActivityDocumentationController::class, 'thumbnail'])->name('dokumentasi.thumbnail');

        Route::post('/{activity}/publikasi', [ActivityPublicationController::class, 'store'])->name('publikasi.store');
        Route::put('/{activity}/publikasi/{publication}', [ActivityPublicationController::class, 'update'])->name('publikasi.update');
        Route::delete('/{activity}/publikasi/{publication}', [ActivityPublicationController::class, 'destroy'])->name('publikasi.destroy');

        Route::get('/{activity}/daftar-hadir', [ActivityAttendanceController::class, 'index'])->name('daftar-hadir.index');
        Route::post('/{activity}/daftar-hadir/settings', [ActivityAttendanceController::class, 'updateSettings'])->name('daftar-hadir.settings');
        Route::post('/{activity}/daftar-hadir', [ActivityAttendanceController::class, 'store'])->name('daftar-hadir.store');
        Route::get('/{activity}/daftar-hadir/respons', [AttendanceResponseController::class, 'index'])->name('daftar-hadir.responses');
        Route::get('/{activity}/daftar-hadir/respons/data', [AttendanceResponseController::class, 'data'])->name('daftar-hadir.responses.data');
        Route::get('/{activity}/daftar-hadir/respons/{response}', [AttendanceResponseController::class, 'show'])->name('daftar-hadir.response.show');
        Route::get('/{activity}/daftar-hadir/polling', [ActivityAttendanceController::class, 'poll'])->name('daftar-hadir.poll');
        Route::get('/{activity}/daftar-hadir/qr', [ActivityAttendanceController::class, 'qr'])->name('daftar-hadir.qr');
        Route::get('/{activity}/daftar-hadir/export-excel', [ActivityAttendanceController::class, 'exportExcel'])->name('daftar-hadir.export-excel');
        Route::get('/{activity}/daftar-hadir/export-pdf', [ActivityAttendanceController::class, 'exportPdf'])->name('daftar-hadir.export-pdf');
        Route::get('/{activity}/daftar-hadir/print', [ActivityAttendanceController::class, 'print'])->name('daftar-hadir.print');
        Route::post('/{activity}/daftar-hadir/buka', [ActivityAttendanceController::class, 'open'])->name('daftar-hadir.open');
        Route::post('/{activity}/daftar-hadir/tutup', [ActivityAttendanceController::class, 'close'])->name('daftar-hadir.close');
        Route::get('/{activity}/daftar-hadir/{response}/edit', [ActivityAttendanceController::class, 'editResponse'])->name('daftar-hadir.response.edit');
        Route::put('/{activity}/daftar-hadir/{response}', [ActivityAttendanceController::class, 'updateResponse'])->name('daftar-hadir.response.update');
        Route::delete('/{activity}/daftar-hadir/{response}', [ActivityAttendanceController::class, 'destroyResponse'])->name('daftar-hadir.response.destroy');

        Route::get('/{activity}/edit', [ActivityController::class, 'edit'])->name('edit');
        Route::get('/{activity}', [ActivityController::class, 'show'])->name('show');
        Route::put('/{activity}', [ActivityController::class, 'update'])->name('update');
        Route::delete('/{activity}', [ActivityController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('superadmin')->group(function () {
        Route::prefix('manajemen-pengguna')->name('users.')->group(function () {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::get('/create', [UserController::class, 'create'])->name('create');
            Route::post('/', [UserController::class, 'store'])->name('store');
            Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
            Route::put('/{user}', [UserController::class, 'update'])->name('update');
            Route::post('/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('toggle-status');
            Route::post('/{user}/reset-password', [UserController::class, 'resetPassword'])->name('reset-password');
        });

        Route::prefix('master-kategori')->name('master.categories.')->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->name('index');
            Route::post('/', [CategoryController::class, 'store'])->name('store');
            Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
            Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
            Route::post('/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])->name('toggle-status');
        });

        Route::prefix('master-unit')->name('master.units.')->group(function () {
            Route::get('/', [UnitController::class, 'index'])->name('index');
            Route::post('/', [UnitController::class, 'store'])->name('store');
            Route::put('/{unit}', [UnitController::class, 'update'])->name('update');
            Route::delete('/{unit}', [UnitController::class, 'destroy'])->name('destroy');
            Route::post('/{unit}/toggle-status', [UnitController::class, 'toggleStatus'])->name('toggle-status');
        });

        Route::get('/log-aktivitas', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    });
});

Route::get('/form/{slug}', [PublicFormController::class, 'show'])
    ->name('public-form.show');

Route::post('/form/{slug}', [PublicFormController::class, 'submit'])
    ->name('public-form.submit');

Route::get('/{slug}', LegacyFormRedirectController::class)
    ->where('slug', '[A-Za-z0-9\-]+')
    ->name('legacy-form.redirect');
