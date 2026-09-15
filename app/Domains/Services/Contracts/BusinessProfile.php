<?php

declare(strict_types=1);

namespace App\Domains\Services\Contracts;

interface BusinessProfile
{
    public function slugFor(string $businessId): string;
}
