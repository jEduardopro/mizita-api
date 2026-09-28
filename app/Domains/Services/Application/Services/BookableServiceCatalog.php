<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\Services;

use App\Domains\Services\Contracts\ServiceAllowance;
use App\Domains\Services\Contracts\ServiceRepository;

final class BookableServiceCatalog
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ServiceAllowance $allowance,
    ) {}

    /**
     * @return list<string>
     */
    public function bookableIdsFor(string $businessId): array
    {
        $active = $this->services->activeIdsOldestFirst($businessId);
        $limit = $this->allowance->activeServiceLimitFor($businessId);

        if ($limit === null) {
            return $active;
        }

        return array_slice($active, 0, $limit);
    }

    public function isBookable(string $businessId, string $serviceId): bool
    {
        return in_array($serviceId, $this->bookableIdsFor($businessId), true);
    }
}
