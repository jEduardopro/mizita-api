<?php

declare(strict_types=1);

namespace App\Domains\Payments\Contracts;

use App\Domains\Payments\Exceptions\PaymentBusinessNotFound;
use App\Shared\ValueObjects\CurrencyCode;

interface BusinessProfile
{
    /**
     * @throws PaymentBusinessNotFound
     */
    public function currencyFor(string $businessId): CurrencyCode;
}
