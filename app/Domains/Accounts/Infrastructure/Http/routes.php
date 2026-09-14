<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Http\Controllers\GoogleIdTokenController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/google', GoogleIdTokenController::class);
