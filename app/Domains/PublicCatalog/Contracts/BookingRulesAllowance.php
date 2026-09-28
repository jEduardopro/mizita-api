<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

interface BookingRulesAllowance
{
    public function includesBookingRules(string $businessId): bool;
}
