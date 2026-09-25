<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Http\Controllers\AccountDeletionController;
use Illuminate\Support\Facades\Route;

Route::get('/me/account/deletion', [AccountDeletionController::class, 'show']);

Route::delete('/me/account', [AccountDeletionController::class, 'destroy']);
