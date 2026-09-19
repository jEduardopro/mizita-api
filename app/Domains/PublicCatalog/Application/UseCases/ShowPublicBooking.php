<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\UseCases;

use App\Domains\PublicCatalog\Application\Dtos\ShowPublicBookingInput;
use App\Domains\PublicCatalog\Contracts\GuestBookings;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestBooking;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class ShowPublicBooking
{
    public function __construct(
        private readonly PublishedBusinesses $businesses,
        private readonly GuestBookings $bookings,
    ) {}

    /**
     * @return UseCaseResponse<PublicGuestBooking>
     */
    public function handle(ShowPublicBookingInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            return UseCaseResponse::success($this->bookings->find(
                $this->businesses->identifyBySlug($input->slug),
                $input->credentials,
            ));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
