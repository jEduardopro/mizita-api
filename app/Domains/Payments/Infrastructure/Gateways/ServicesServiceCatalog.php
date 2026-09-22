<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Gateways;

use App\Domains\Payments\Contracts\ServiceCatalog;
use App\Domains\Payments\Exceptions\PaymentServiceNotFound;
use App\Domains\Payments\ValueObjects\Money;
use App\Domains\Payments\ValueObjects\ServiceSnapshot;
use App\Domains\Services\Contracts\ServiceRepository;
use App\Domains\Services\Exceptions\ServiceNotFound;
use App\Shared\ValueObjects\CurrencyCode;

final class ServicesServiceCatalog implements ServiceCatalog
{
    public function __construct(
        private readonly ServiceRepository $services,
    ) {}

    public function describe(string $businessId, string $serviceId, CurrencyCode $currency): ServiceSnapshot
    {
        try {
            $service = $this->services->findIncludingArchived($businessId, $serviceId);
        } catch (ServiceNotFound) {
            throw PaymentServiceNotFound::withId($serviceId);
        }

        return new ServiceSnapshot(
            id: $service->id,
            name: $service->name(),
            price: Money::fromDecimalString($service->price(), $currency),
        );
    }
}
