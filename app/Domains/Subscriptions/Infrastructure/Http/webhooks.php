<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Infrastructure\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/stripe', [StripeWebhookController::class, 'store']);
