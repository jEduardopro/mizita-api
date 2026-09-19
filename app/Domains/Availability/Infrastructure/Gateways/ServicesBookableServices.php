<?php

declare(strict_types=1);

namespace App\Domains\Availability\Infrastructure\Gateways;

use App\Domains\Availability\Contracts\BookableServices;
use App\Domains\Availability\Exceptions\BookableServiceNotFound;
use App\Domains\Availability\ValueObjects\BookableService;
use App\Domains\Services\Contracts\ServiceRepository;
use App\Domains\Services\Entities\Service;
use App\Domains\Services\Exceptions\ServiceNotFound;

final class ServicesBookableServices implements BookableServices
{
    public function __construct(
        private readonly ServiceRepository $services,
    ) {}

    public function describe(string $businessId, string $serviceId): BookableService
    {
        $service = $this->offeredService($businessId, $serviceId);

        if (! $service->isActive()) {
            throw BookableServiceNotFound::withId($serviceId);
        }

        return new BookableService(
            id: $service->id,
            durationMinutes: $service->durationMinutes(),
            bufferAfterMinutes: $service->bufferMinutes(),
            staffIds: $service->staffIds(),
        );
    }

    /**
     * @throws BookableServiceNotFound
     */
    private function offeredService(string $businessId, string $serviceId): Service
    {
        try {
            return $this->services->findForBusiness($businessId, $serviceId);
        } catch (ServiceNotFound $missing) {
            throw BookableServiceNotFound::withId($serviceId, $missing);
        }
    }
}
