<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Gateways;

use App\Domains\Appointments\Contracts\ServiceCatalog;
use App\Domains\Appointments\Exceptions\AppointmentServiceNotFound;
use App\Domains\Appointments\ValueObjects\ServiceSnapshot;
use App\Domains\Services\Contracts\ServiceRepository;
use App\Domains\Services\Entities\Service;
use App\Domains\Services\Exceptions\ServiceNotFound;

final class ServicesServiceCatalog implements ServiceCatalog
{
    public function __construct(
        private readonly ServiceRepository $services,
    ) {}

    public function describe(string $businessId, string $serviceId): ServiceSnapshot
    {
        try {
            return self::snapshotOf($this->services->findIncludingArchived($businessId, $serviceId));
        } catch (ServiceNotFound $missing) {
            throw AppointmentServiceNotFound::withId($serviceId, $missing);
        }
    }

    /**
     * @param  list<string>  $serviceIds
     * @return array<string, ServiceSnapshot>
     */
    public function describeMany(string $businessId, array $serviceIds): array
    {
        if ($serviceIds === []) {
            return [];
        }

        $wanted = array_values(array_unique($serviceIds));
        $snapshots = $this->activeSnapshotsAmong($businessId, $wanted);

        foreach ($wanted as $serviceId) {
            if (! isset($snapshots[$serviceId])) {
                $snapshots[$serviceId] = $this->describe($businessId, $serviceId);
            }
        }

        return $snapshots;
    }

    /**
     * @param  list<string>  $wanted
     * @return array<string, ServiceSnapshot>
     */
    private function activeSnapshotsAmong(string $businessId, array $wanted): array
    {
        $snapshots = [];

        foreach ($this->services->activeForBusiness($businessId) as $service) {
            if (in_array($service->id, $wanted, true)) {
                $snapshots[$service->id] = self::snapshotOf($service);
            }
        }

        return $snapshots;
    }

    private static function snapshotOf(Service $service): ServiceSnapshot
    {
        return new ServiceSnapshot(
            id: $service->id,
            name: $service->name(),
            color: $service->color()->value,
            durationMinutes: $service->durationMinutes(),
            active: $service->isActive(),
        );
    }
}
