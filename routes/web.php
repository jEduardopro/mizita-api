<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('public/welcome'));

Route::get('/terms', fn () => Inertia::render('public/legal/terms'))->name('legal.terms');
Route::get('/privacy', fn () => Inertia::render('public/legal/privacy'))->name('legal.privacy');
Route::get('/cookies', fn () => Inertia::render('public/legal/cookies'))->name('legal.cookies');

Route::get('/onboarding', fn () => Inertia::render('admin/onboarding'))
    ->middleware(['auth', 'onboarding'])
    ->name('onboarding');

Route::permanentRedirect('/dashboard', '/calendar')->name('dashboard');

Route::middleware(['auth', 'onboarded'])->group(function (): void {
    Route::get('/calendar', fn () => Inertia::render('admin/calendar'))->name('calendar');
    Route::get('/services', fn () => Inertia::render('admin/services'))->name('services');
    Route::get('/customers', fn () => Inertia::render('admin/customers'))->name('customers');
    Route::get('/settings/profile', fn () => Inertia::render('admin/settings/profile'))->name('settings.profile');
});
