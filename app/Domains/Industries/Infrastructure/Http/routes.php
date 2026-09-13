<?php

declare(strict_types=1);

use App\Domains\Industries\Infrastructure\Http\Controllers\IndustryController;
use Illuminate\Support\Facades\Route;

/*
| Routes owned by the Industries domain. Loaded by IndustriesServiceProvider
| under the "api" prefix and middleware group.
|
| Read only: the catalog is seeded, so there is no write endpoint to add.
*/

Route::get('/industries', [IndustryController::class, 'index']);
