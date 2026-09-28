<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

interface PublishedStaffServices
{
    public function offeredServiceIdFor(string $businessId, string $staffMemberId, string $serviceSlug): ?string;
}
