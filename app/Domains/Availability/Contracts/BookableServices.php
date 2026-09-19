<?php

declare(strict_types=1);

namespace App\Domains\Availability\Contracts;

use App\Domains\Availability\Exceptions\BookableServiceNotFound;
use App\Domains\Availability\ValueObjects\BookableService;

interface BookableServices
{
    /**
     * @throws BookableServiceNotFound
     */
    public function describe(string $businessId, string $serviceId): BookableService;
}
