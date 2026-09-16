<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\PublicCatalog\Contracts\PublishedServices;
use App\Domains\PublicCatalog\ValueObjects\PublicService;
use App\Domains\Services\Contracts\ServiceImages;
use App\Domains\Services\Contracts\ServiceRepository;
use App\Domains\Services\Entities\Service;

final class ServicesPublishedServices implements PublishedServices
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ServiceImages $images,
    ) {}

    /**
     * @return list<PublicService>
     */
    public function forBusiness(string $businessId): array
    {
        $services = $this->services->activeForBusiness($businessId);

        if ($services === []) {
            return [];
        }

        $imageUrls = $this->images->urlsFor(array_map(
            static fn (Service $service): string => $service->id,
            $services,
        ));

        return array_map(
            static fn (Service $service): PublicService => new PublicService(
                id: $service->id,
                name: $service->name(),
                slug: $service->slug(),
                description: $service->description(),
                durationMinutes: $service->durationMinutes(),
                price: $service->price(),
                imageUrl: $imageUrls[$service->id] ?? null,
            ),
            $services,
        );
    }
}
