<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Staff\Contracts\BusinessSlugs;

final class BusinessesBusinessSlugs implements BusinessSlugs
{
    public function __construct(
        private readonly BusinessRepository $businesses,
    ) {}

    public function slugOf(string $businessId): string
    {
        return $this->businesses->findById($businessId)->slug();
    }
}
