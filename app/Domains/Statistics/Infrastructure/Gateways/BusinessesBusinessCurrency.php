<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Statistics\Contracts\BusinessCurrency;
use App\Shared\ValueObjects\CurrencyCode;

final class BusinessesBusinessCurrency implements BusinessCurrency
{
    public function __construct(
        private readonly BusinessRepository $businesses,
    ) {}

    public function currencyOf(string $businessId): CurrencyCode
    {
        return CurrencyCode::restore($this->businesses->findById($businessId)->currency());
    }
}
