<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Http\Controllers\AccountReactivationController;
use Illuminate\Support\Facades\Route;

Route::get('/account-reactivation', [AccountReactivationController::class, 'show']);

Route::post('/account-reactivation', [AccountReactivationController::class, 'store']);

Route::delete('/account-reactivation', [AccountReactivationController::class, 'destroy']);
