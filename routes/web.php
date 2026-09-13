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

// Only `auth` here: a freshly registered user has no business yet, and the
// `business` middleware aborts 403 when the caller has none.
Route::get('/dashboard', fn () => Inertia::render('admin/dashboard'))
    ->middleware('auth')
    ->name('dashboard');
