<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Contracts;

interface OpeningHours
{
    public function isOpenNow(string $businessId): bool;
}
