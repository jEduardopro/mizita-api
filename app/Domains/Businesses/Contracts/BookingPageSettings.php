<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

use App\Domains\Businesses\ValueObjects\BookingPageSnapshot;
use App\Domains\Businesses\ValueObjects\BookingPageStyle;

interface BookingPageSettings
{
    public function forBusiness(string $businessId): BookingPageSnapshot;

    public function applyTo(string $businessId, BookingPageStyle $style): void;
}
