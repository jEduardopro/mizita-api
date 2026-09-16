<?php

declare(strict_types=1);

namespace Tests\Support\BookingPages;

use App\Domains\BookingPages\Contracts\CurrentBookingPage;
use App\Domains\BookingPages\Entities\BookingPage;

final class FakeCurrentBookingPage implements CurrentBookingPage
{
    /**
     * @var list<string>
     */
    public array $businessIdsSeen = [];

    public function __construct(
        private readonly BookingPage $page,
    ) {}

    public function forBusiness(string $businessId): BookingPage
    {
        $this->businessIdsSeen[] = $businessId;

        return $this->page;
    }
}
