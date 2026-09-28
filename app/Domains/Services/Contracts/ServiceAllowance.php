<?php

declare(strict_types=1);

namespace App\Domains\Services\Contracts;

interface ServiceAllowance
{
    public function activeServiceLimitFor(string $businessId): ?int;
}
