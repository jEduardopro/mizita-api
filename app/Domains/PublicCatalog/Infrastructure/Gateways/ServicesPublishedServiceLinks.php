<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\PublicCatalog\Contracts\PublishedServiceLinks;
use App\Domains\Services\Application\Services\BookableServiceCatalog;
use App\Domains\Services\Contracts\ServiceRepository;

final class ServicesPublishedServiceLinks implements PublishedServiceLinks
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly BookableServiceCatalog $bookableServices,
    ) {}

    public function bookableServiceIdFor(string $businessId, string $serviceSlug): ?string
    {
        $serviceId = $this->activeServiceIdFor($businessId, $serviceSlug);

        if ($serviceId === null || ! $this->bookableServices->isBookable($businessId, $serviceId)) {
            return null;
        }

        return $serviceId;
    }

    private function activeServiceIdFor(string $businessId, string $serviceSlug): ?string
    {
        foreach ($this->services->activeForBusiness($businessId) as $service) {
            if ($service->slug() === $serviceSlug) {
                return $service->id;
            }
        }

        return null;
    }
}
