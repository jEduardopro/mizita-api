<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Laravel owns routing; Inertia only names the page component to render. Pages
// carry identity and route parameters, never data - they read that from /api.
Route::get('/', fn () => Inertia::render('public/welcome'));

// The legal documents. Public and unauthenticated by design: the signup form
// asks people to accept them before they have an account, so gating them behind
// one would make that acceptance impossible to give informedly. The text itself
// lives in resources/js/content/legal, versioned with the code that describes it.
Route::get('/terms', fn () => Inertia::render('public/legal/terms'))->name('legal.terms');
Route::get('/privacy', fn () => Inertia::render('public/legal/privacy'))->name('legal.privacy');
Route::get('/cookies', fn () => Inertia::render('public/legal/cookies'))->name('legal.cookies');

// The two halves of onboarding, each guarded by the state it expects: an
// account with no business belongs on /onboarding, one with a business belongs
// on /dashboard, and whichever page the caller asks for the wrong way round
// redirects to the other. That pair is why fortify's `home` can stay
// /dashboard - a new account lands there and is bounced on - and why neither
// the login flow nor the Google callback needs to know about onboarding.
//
// Neither route carries `business`: onboarding is what produces the membership
// that middleware demands, so requiring one here would close signup.
Route::get('/onboarding', fn () => Inertia::render('admin/onboarding'))
    ->middleware(['auth', 'onboarding'])
    ->name('onboarding');

Route::get('/dashboard', fn () => Inertia::render('admin/dashboard'))
    ->middleware(['auth', 'onboarded'])
    ->name('dashboard');
