<?php

declare(strict_types=1);

use App\Domains\Payments\Infrastructure\Http\Controllers\PaymentMethodCatalogController;
use App\Domains\Payments\Infrastructure\Http\Controllers\PaymentReportController;
use Illuminate\Support\Facades\Route;

Route::get('/payments/sales', [PaymentReportController::class, 'sales']);

Route::get('/payments/transactions', [PaymentReportController::class, 'transactions']);

Route::get('/payment-method-catalog', [PaymentMethodCatalogController::class, 'index']);
