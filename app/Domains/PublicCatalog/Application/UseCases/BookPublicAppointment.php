<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\UseCases;

use App\Domains\PublicCatalog\Application\Dtos\BookPublicAppointmentInput;
use App\Domains\PublicCatalog\Contracts\GuestBookingDesk;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestBookingConfirmation;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class BookPublicAppointment
{
    public function __construct(
        private readonly PublishedBusinesses $businesses,
        private readonly GuestBookingDesk $desk,
    ) {}

    /**
     * @return UseCaseResponse<PublicGuestBookingConfirmation>
     */
    public function handle(BookPublicAppointmentInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            return UseCaseResponse::success($this->desk->book(
                $this->businesses->identifyBySlug($input->slug),
                $input->booking,
            ));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
