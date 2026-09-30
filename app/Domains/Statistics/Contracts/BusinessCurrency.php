<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Contracts;

use App\Shared\ValueObjects\CurrencyCode;

interface BusinessCurrency
{
    public function currencyOf(string $businessId): CurrencyCode;
}
