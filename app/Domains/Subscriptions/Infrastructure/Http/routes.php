<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Infrastructure\Http\Controllers\BillingPortalSessionController;
use App\Domains\Subscriptions\Infrastructure\Http\Controllers\PlanController;
use App\Domains\Subscriptions\Infrastructure\Http\Controllers\SubscriptionCheckoutController;
use App\Domains\Subscriptions\Infrastructure\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/subscription', [SubscriptionController::class, 'show']);

Route::get('/plans', [PlanController::class, 'index']);

Route::post('/subscription/checkout', [SubscriptionCheckoutController::class, 'store']);

Route::post('/subscription/checkout/{session}/confirm', [SubscriptionCheckoutController::class, 'confirm']);

Route::post('/subscription/switch-to-free', [SubscriptionController::class, 'switchToFree']);

Route::post('/subscription/resume', [SubscriptionController::class, 'resume']);

Route::post('/subscription/billing-portal', [BillingPortalSessionController::class, 'store']);
