<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

interface PublishedBookingHorizon
{
    public function lastBookableDateFor(string $businessId): string;
}
