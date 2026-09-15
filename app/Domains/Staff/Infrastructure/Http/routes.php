<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Http\Controllers\StaffMemberController;
use Illuminate\Support\Facades\Route;

Route::get('/staff', [StaffMemberController::class, 'index']);
