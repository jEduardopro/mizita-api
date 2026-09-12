<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Http\Controllers\GoogleIdTokenController;
use Illuminate\Support\Facades\Route;

/*
| API routes owned by the Accounts domain. Loaded by AccountsServiceProvider
| under the "api" prefix, with no auth middleware: this endpoint is the sign in.
*/

Route::post('/auth/google', GoogleIdTokenController::class);
