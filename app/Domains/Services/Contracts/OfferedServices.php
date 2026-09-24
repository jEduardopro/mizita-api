<?php

declare(strict_types=1);

namespace App\Domains\Services\Contracts;

use App\Domains\Services\Entities\Service;

interface OfferedServices
{
    /**
     * @return list<Service>
     */
    public function offeredBy(string $businessId, string $staffId): array;
}
