<?php

declare(strict_types=1);

namespace Tests\Support\Appointments;

use App\Domains\Appointments\Contracts\ServiceCatalog;
use App\Domains\Appointments\Exceptions\AppointmentServiceNotFound;
use App\Domains\Appointments\ValueObjects\ServiceSnapshot;

final class FakeServiceCatalog implements ServiceCatalog
{
    /**
     * @var array<string, array<string, ServiceSnapshot>>
     */
    private array $servicesByBusiness = [];

    /**
     * @var list<array{businessId: string, serviceId: string}>
     */
    public array $reads = [];

    /**
     * @var list<array{businessId: string, serviceIds: list<string>}>
     */
    public array $batchReads = [];

    public function __construct(
        public readonly AppointmentJournal $journal = new AppointmentJournal,
    ) {}

    public static function of(string $businessId, ServiceSnapshot ...$services): self
    {
        return (new self)->add($businessId, ...$services);
    }

    public function add(string $businessId, ServiceSnapshot ...$services): self
    {
        foreach ($services as $service) {
            $this->servicesByBusiness[$businessId][$service->id] = $service;
        }

        return $this;
    }

    public function describe(string $businessId, string $serviceId): ServiceSnapshot
    {
        $this->journal->record('services.describe');
        $this->reads[] = ['businessId' => $businessId, 'serviceId' => $serviceId];

        return $this->servicesByBusiness[$businessId][$serviceId]
            ?? throw AppointmentServiceNotFound::withId($serviceId);
    }

    /**
     * @param  list<string>  $serviceIds
     * @return array<string, ServiceSnapshot>
     */
    public function describeMany(string $businessId, array $serviceIds): array
    {
        $this->journal->record('services.describeMany');
        $this->batchReads[] = ['businessId' => $businessId, 'serviceIds' => array_values($serviceIds)];

        $known = $this->servicesByBusiness[$businessId] ?? [];
        $found = [];

        foreach ($serviceIds as $serviceId) {
            if (isset($known[$serviceId])) {
                $found[$serviceId] = $known[$serviceId];
            }
        }

        return $found;
    }
}
