<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Services\Contracts\BusinessProfile;

final class BusinessesBusinessProfile implements BusinessProfile
{
    public function __construct(
        private readonly BusinessRepository $businesses,
    ) {}

    public function slugFor(string $businessId): string
    {
        return $this->businesses->findById($businessId)->slug();
    }
}
