<?php

declare(strict_types=1);

namespace App\Domains\Payments\Contracts;

use App\Domains\Payments\Entities\PaymentMethod;
use App\Domains\Payments\Exceptions\PaymentMethodNotEnabled;
use App\Domains\Payments\Exceptions\PaymentMethodNotFound;
use App\Domains\Payments\ValueObjects\AvailablePaymentMethod;

interface PaymentMethodCatalog
{
    /**
     * @return list<AvailablePaymentMethod>
     */
    public function availableFor(string $businessId): array;

    /**
     * @throws PaymentMethodNotFound
     * @throws PaymentMethodNotEnabled
     */
    public function findEnabledFor(string $businessId, string $paymentMethodId): PaymentMethod;

    /**
     * @param  list<string>  $paymentMethodIds
     * @return array<string, PaymentMethod>
     */
    public function describeMany(array $paymentMethodIds): array;
}
