<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\Appointments\Application\Dtos\GuestBookingCredentials;
use App\Domains\Appointments\Application\Dtos\ShowGuestBookingInput;
use App\Domains\Appointments\Application\UseCases\ShowGuestBooking;
use App\Domains\PublicCatalog\Contracts\GuestBookings;
use App\Domains\PublicCatalog\Infrastructure\Mappers\GuestBookingMapper;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingCredentials;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestBooking;

final class AppointmentsGuestBookings implements GuestBookings
{
    public function __construct(
        private readonly ShowGuestBooking $showGuestBooking,
        private readonly GuestBookingMapper $mapper,
    ) {}

    public function find(string $businessId, PublicBookingCredentials $credentials): PublicGuestBooking
    {
        $response = $this->showGuestBooking->handle(new ShowGuestBookingInput(
            businessId: $businessId,
            credentials: new GuestBookingCredentials(
                referenceCode: $credentials->referenceCode,
                manageToken: $credentials->manageToken,
            ),
        ));

        return $this->mapper->toPublicBooking($response->value());
    }
}
