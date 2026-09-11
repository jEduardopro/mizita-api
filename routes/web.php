<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Laravel owns routing; Inertia only names the page component to render. Pages
// carry identity and route parameters, never data - they read that from /api.
Route::get('/', fn () => Inertia::render('public/welcome'));

// Only `auth` here: a freshly registered user has no business yet, and the
// `business` middleware aborts 403 when the caller has none.
Route::get('/dashboard', fn () => Inertia::render('admin/dashboard'))
    ->middleware('auth')
    ->name('dashboard');
