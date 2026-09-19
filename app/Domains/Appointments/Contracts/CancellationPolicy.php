<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Contracts;

use App\Domains\Appointments\ValueObjects\CancellationRule;

interface CancellationPolicy
{
    public function forBusiness(string $businessId): CancellationRule;
}
