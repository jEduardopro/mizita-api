<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\UseCases;

use App\Domains\PublicCatalog\Application\Dtos\ReschedulePublicBookingInput;
use App\Domains\PublicCatalog\Contracts\GuestBookingDesk;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestBooking;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class ReschedulePublicBooking
{
    public function __construct(
        private readonly PublishedBusinesses $businesses,
        private readonly GuestBookingDesk $desk,
    ) {}

    /**
     * @return UseCaseResponse<PublicGuestBooking>
     */
    public function handle(ReschedulePublicBookingInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            return UseCaseResponse::success($this->desk->reschedule(
                $this->businesses->identifyBySlug($input->slug),
                $input->credentials,
                $input->startsAt,
            ));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
