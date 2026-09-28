<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\PublicCatalog\Contracts\PublishedServiceLinks;
use App\Domains\Services\Contracts\ServiceRepository;

final class ServicesPublishedServiceLinks implements PublishedServiceLinks
{
    public function __construct(
        private readonly ServiceRepository $services,
    ) {}

    public function bookableServiceIdFor(string $businessId, string $serviceSlug): ?string
    {
        foreach ($this->services->activeForBusiness($businessId) as $service) {
            if ($service->slug() === $serviceSlug) {
                return $service->id;
            }
        }

        return null;
    }
}
