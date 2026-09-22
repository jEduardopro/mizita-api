<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Payments\Contracts\BusinessProfile;
use App\Domains\Payments\Exceptions\PaymentBusinessNotFound;
use App\Shared\ValueObjects\CurrencyCode;

final class BusinessesBusinessProfile implements BusinessProfile
{
    public function __construct(
        private readonly BusinessRepository $businesses,
    ) {}

    public function currencyFor(string $businessId): CurrencyCode
    {
        try {
            $business = $this->businesses->findById($businessId);
        } catch (BusinessNotFound) {
            throw PaymentBusinessNotFound::withId($businessId);
        }

        return CurrencyCode::restore($business->currency());
    }
}
