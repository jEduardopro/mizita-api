<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\PaymentMethodProvisioner;
use App\Domains\Payments\Contracts\BusinessPaymentMethods;

final class PaymentsPaymentMethodProvisioner implements PaymentMethodProvisioner
{
    public function __construct(
        private readonly BusinessPaymentMethods $paymentMethods,
    ) {}

    public function provisionFor(string $businessId): void
    {
        $this->paymentMethods->enableDefaultsFor($businessId);
    }
}
