<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('public/welcome'));

// Unauthenticated by design: the signup form asks people to accept these before
// they have an account, so gating them would make that acceptance impossible to
// give informedly.
Route::get('/terms', fn () => Inertia::render('public/legal/terms'))->name('legal.terms');
Route::get('/privacy', fn () => Inertia::render('public/legal/privacy'))->name('legal.privacy');
Route::get('/cookies', fn () => Inertia::render('public/legal/cookies'))->name('legal.cookies');

// Each half guarded by the state it expects, which is why fortify's `home` can
// stay /dashboard: a new account lands there and is bounced on, so neither the
// login flow nor the Google callback needs to know about onboarding.
//
// Neither route carries `business`: onboarding is what produces the membership
// that middleware demands, so requiring one here would close signup.
Route::get('/onboarding', fn () => Inertia::render('admin/onboarding'))
    ->middleware(['auth', 'onboarding'])
    ->name('onboarding');

Route::get('/dashboard', fn () => Inertia::render('admin/dashboard'))
    ->middleware(['auth', 'onboarded'])
    ->name('dashboard');
