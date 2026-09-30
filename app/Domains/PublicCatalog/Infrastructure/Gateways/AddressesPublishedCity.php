<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\Addresses\Contracts\AddressRepository;
use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use App\Domains\PublicCatalog\Contracts\PublishedCity;

final class AddressesPublishedCity implements PublishedCity
{
    public function __construct(
        private readonly AddressRepository $addresses,
    ) {}

    public function forBusiness(string $businessId): ?string
    {
        return $this->addresses->findForOwner(AddressOwnerType::Business, $businessId)?->city();
    }
}
