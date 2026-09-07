<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
 * NO existe ruta de alta pública ('register') a propósito.
 *
 * En este CRM las cuentas se crean por SSO (provisión JIT desde el wrapper,
 * ver App\Modules\Integracion) o por administración. Una ruta de registro
 * abierta permitía que cualquiera en Internet creara una cuenta activa
 * (users.activo tiene DEFAULT 1) y atravesara el guard 'auth'.
 *
 * La cobertura está en tests/Feature/Auth/RegistrationTest.php, que falla si
 * alguien vuelve a registrarla. La vista welcome/navigation.blade.php ya usa
 * Route::has('register'), así que se adapta sola a su ausencia.
 */

Route::middleware('guest')->group(function () {
    Volt::route('login', 'pages.auth.login')
        ->name('login');

    Volt::route('forgot-password', 'pages.auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'pages.auth.reset-password')
        ->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Volt::route('verify-email', 'pages.auth.verify-email')
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Volt::route('confirm-password', 'pages.auth.confirm-password')
        ->name('password.confirm');
});
