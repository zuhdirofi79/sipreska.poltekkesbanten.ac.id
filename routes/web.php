<?php

use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LegacyFormRedirectController;
use App\Http\Controllers\PublicFormController;
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

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/ganti-password', [ChangePasswordController::class, 'edit'])->name('password.change');
    Route::put('/ganti-password', [ChangePasswordController::class, 'update'])->name('password.update');
});

Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('superadmin')->prefix('manajemen-pengguna')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::post('/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('toggle-status');
        Route::post('/{user}/reset-password', [UserController::class, 'resetPassword'])->name('reset-password');
    });
});

Route::get('/form/{slug}', [PublicFormController::class, 'show'])
    ->name('public-form.show');

Route::post('/form/{slug}', [PublicFormController::class, 'submit'])
    ->name('public-form.submit');

Route::get('/{slug}', LegacyFormRedirectController::class)
    ->where('slug', '[A-Za-z0-9\-]+')
    ->name('legacy-form.redirect');
