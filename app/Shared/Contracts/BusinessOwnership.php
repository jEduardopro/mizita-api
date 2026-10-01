<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface BusinessOwnership
{
    public function ownsOpenBusiness(string $accountId): bool;
}
