<?php

declare(strict_types=1);

namespace Tests\Support\Payments;

use App\Domains\Payments\Contracts\ServiceCatalog;
use App\Domains\Payments\Exceptions\PaymentServiceNotFound;
use App\Domains\Payments\ValueObjects\Money;
use App\Domains\Payments\ValueObjects\ServiceSnapshot;
use App\Shared\ValueObjects\CurrencyCode;

final class FakeServiceCatalog implements ServiceCatalog
{
    /**
     * @var array<string, array<string, array{name: string, priceCents: int}>>
     */
    private array $servicesByBusiness = [];

    private ?CurrencyCode $forcedCurrency = null;

    /**
     * @var list<array{businessId: string, serviceId: string, currencyCode: string}>
     */
    public array $reads = [];

    public function __construct(
        public readonly PaymentJournal $journal = new PaymentJournal,
    ) {}

    public function add(string $businessId, string $serviceId, string $name, int $priceCents): self
    {
        $this->servicesByBusiness[$businessId][$serviceId] = ['name' => $name, 'priceCents' => $priceCents];

        return $this;
    }

    public function pricedIn(CurrencyCode $currency): self
    {
        $this->forcedCurrency = $currency;

        return $this;
    }

    public function describe(string $businessId, string $serviceId, CurrencyCode $currency): ServiceSnapshot
    {
        $this->journal->record('services.describe');
        $this->reads[] = [
            'businessId' => $businessId,
            'serviceId' => $serviceId,
            'currencyCode' => $currency->value,
        ];

        $service = $this->servicesByBusiness[$businessId][$serviceId]
            ?? throw PaymentServiceNotFound::withId($serviceId);

        return new ServiceSnapshot(
            id: $serviceId,
            name: $service['name'],
            price: Money::fromCents($service['priceCents'], $this->forcedCurrency ?? $currency),
        );
    }
}
