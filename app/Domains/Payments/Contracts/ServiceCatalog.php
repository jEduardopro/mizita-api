<?php

declare(strict_types=1);

namespace App\Domains\Payments\Contracts;

use App\Domains\Payments\Exceptions\PaymentServiceNotFound;
use App\Domains\Payments\ValueObjects\ServiceSnapshot;
use App\Shared\ValueObjects\CurrencyCode;

interface ServiceCatalog
{
    /**
     * @throws PaymentServiceNotFound
     */
    public function describe(string $businessId, string $serviceId, CurrencyCode $currency): ServiceSnapshot;
}
