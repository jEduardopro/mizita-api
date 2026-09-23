<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Presenters;

use App\Domains\Businesses\Application\Dtos\BusinessSettingsData;
use App\Domains\Businesses\Contracts\BookingPageSettings;
use App\Domains\Businesses\Contracts\BookingPolicySettings;
use App\Domains\Businesses\Contracts\BusinessAddressBook;
use App\Domains\Businesses\Contracts\BusinessLinkList;
use App\Domains\Businesses\Contracts\BusinessLogo;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Contracts\BusinessSchedule;
use App\Domains\Businesses\Contracts\PhoneBook;
use App\Domains\Businesses\Exceptions\BusinessNotFound;

final class BusinessSettingsPresenter
{
    public function __construct(
        private readonly BusinessRepository $businesses,
        private readonly BusinessAddressBook $addresses,
        private readonly BusinessLinkList $links,
        private readonly BusinessSchedule $schedule,
        private readonly BookingPageSettings $bookingPages,
        private readonly BookingPolicySettings $bookingPolicies,
        private readonly PhoneBook $phones,
        private readonly BusinessLogo $logo,
    ) {}

    /**
     * @throws BusinessNotFound
     */
    public function describe(string $businessId): BusinessSettingsData
    {
        $business = $this->businesses->findById($businessId);

        return new BusinessSettingsData(
            id: $business->id,
            name: $business->name(),
            slug: $business->slug(),
            industryId: $business->industryId(),
            timezone: $business->timezone(),
            about: $business->about(),
            contactEmail: $business->contactEmail(),
            currencyCode: $business->currency(),
            logoUrl: $this->logo->urlFor($businessId),
            phone: $this->phones->forBusiness($businessId),
            address: $this->addresses->forBusiness($businessId),
            schedule: $this->schedule->forBusiness($businessId),
            links: $this->links->forBusiness($businessId),
            bookingPage: $this->bookingPages->forBusiness($businessId),
            bookingPolicy: $this->bookingPolicies->forBusiness($businessId),
            contactFields: $this->bookingPolicies->contactFieldsFor($businessId),
        );
    }
}
