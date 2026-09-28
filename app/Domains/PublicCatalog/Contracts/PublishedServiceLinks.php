<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

interface PublishedServiceLinks
{
    public function bookableServiceIdFor(string $businessId, string $serviceSlug): ?string;
}
