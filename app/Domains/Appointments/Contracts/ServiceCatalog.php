<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Contracts;

use App\Domains\Appointments\Exceptions\AppointmentServiceNotFound;
use App\Domains\Appointments\ValueObjects\ServiceSnapshot;

interface ServiceCatalog
{
    /**
     * @throws AppointmentServiceNotFound
     */
    public function describe(string $businessId, string $serviceId): ServiceSnapshot;

    /**
     * @param  list<string>  $serviceIds
     * @return array<string, ServiceSnapshot>
     */
    public function describeMany(string $businessId, array $serviceIds): array;
}
