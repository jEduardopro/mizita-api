<?php

declare(strict_types=1);

use App\Domains\Payments\Infrastructure\Http\Controllers\AppointmentPaymentController;
use App\Domains\Payments\Infrastructure\Http\Controllers\PaymentMethodController;
use App\Domains\Payments\Infrastructure\Http\Controllers\PaymentTransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/payment-methods', [PaymentMethodController::class, 'index'])
    ->middleware('permission:view_payments');

Route::get('/appointments/{appointment}/payment', [AppointmentPaymentController::class, 'show'])
    ->middleware('permission:view_payments')
    ->whereUuid('appointment');

Route::post('/appointments/{appointment}/payment', [AppointmentPaymentController::class, 'store'])
    ->middleware('permission:create_payment')
    ->whereUuid('appointment');

Route::post('/payments/{payment}/transactions', [PaymentTransactionController::class, 'store'])
    ->middleware('permission:create_payment')
    ->whereUuid('payment');

Route::post('/payments/{payment}/transactions/{transaction}/void', [PaymentTransactionController::class, 'void'])
    ->middleware('permission:void_payment_transaction')
    ->whereUuid(['payment', 'transaction']);
