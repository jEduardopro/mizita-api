<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Contracts;

use App\Domains\BookingPages\Entities\BookingPage;
use App\Domains\BookingPages\Exceptions\BookingPageNotFound;

interface BookingPageRepository
{
    public function findForBusiness(string $businessId): ?BookingPage;

    /**
     * @throws BookingPageNotFound
     */
    public function ofBusiness(string $businessId): BookingPage;

    public function save(BookingPage $page): void;
}
