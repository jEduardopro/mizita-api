<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\UseCases;

use App\Domains\PublicCatalog\Application\Dtos\BookPublicAppointmentInput;
use App\Domains\PublicCatalog\Contracts\GuestBookingDesk;
use App\Domains\PublicCatalog\Contracts\GuestContactFields;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Contracts\PublishedLocation;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestBookingConfirmation;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\CountryCode;

final class BookPublicAppointment
{
    private const FALLBACK_ADDRESS_COUNTRY = CountryCode::Mx;

    public function __construct(
        private readonly PublishedBusinesses $businesses,
        private readonly GuestContactFields $contactFields,
        private readonly PublishedLocation $location,
        private readonly GuestBookingDesk $desk,
    ) {}

    /**
     * @return UseCaseResponse<PublicGuestBookingConfirmation>
     */
    public function handle(BookPublicAppointmentInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->businesses->identifyBySlug($input->slug);

            return UseCaseResponse::success($this->desk->book(
                $businessId,
                $input->booking->collectingOnly($this->contactFields->forBusiness($businessId)),
                $this->addressCountryOf($businessId),
            ));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    private function addressCountryOf(string $businessId): string
    {
        return $this->location->forBusiness($businessId)?->countryCode
            ?? self::FALLBACK_ADDRESS_COUNTRY->value;
    }
}
