<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Infrastructure;

use App\Domains\BookingPages\Contracts\BookingPageRepository;
use App\Domains\BookingPages\Contracts\CurrentBookingPage;
use App\Domains\BookingPages\Entities\BookingPage;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;

final class ProvisionedCurrentBookingPage implements CurrentBookingPage
{
    public function __construct(
        private readonly BookingPageRepository $pages,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {}

    public function forBusiness(string $businessId): BookingPage
    {
        $page = $this->pages->findForBusiness($businessId);

        if ($page !== null) {
            return $page;
        }

        $page = BookingPage::withDefaults(
            id: $this->ids->next(),
            businessId: $businessId,
            now: $this->clock->now(),
        );

        $this->pages->save($page);

        return $page;
    }
}
