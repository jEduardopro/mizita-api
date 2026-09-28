<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\PublicCatalog\Contracts\PublishedStaffServices;
use App\Domains\Services\Contracts\OfferedServices;

final class ServicesPublishedStaffServices implements PublishedStaffServices
{
    public function __construct(
        private readonly OfferedServices $offeredServices,
    ) {}

    public function offeredServiceIdFor(string $businessId, string $staffMemberId, string $serviceSlug): ?string
    {
        foreach ($this->offeredServices->offeredBy($businessId, $staffMemberId) as $service) {
            if ($service->isActive() && $service->slug() === $serviceSlug) {
                return $service->id;
            }
        }

        return null;
    }
}
