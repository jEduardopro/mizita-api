<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Presenters;

use App\Domains\Businesses\Application\Dtos\BusinessSettingsData;
use App\Domains\Businesses\Contracts\BookingPageSettings;
use App\Domains\Businesses\Contracts\BookingPolicySettings;
use App\Domains\Businesses\Contracts\BookingRulesAllowance;
use App\Domains\Businesses\Contracts\BusinessAddressBook;
use App\Domains\Businesses\Contracts\BusinessLinkList;
use App\Domains\Businesses\Contracts\BusinessLogo;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Contracts\BusinessSchedule;
use App\Domains\Businesses\Contracts\PhoneBook;
use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\ValueObjects\BookingPolicySnapshot;
use App\Domains\Businesses\ValueObjects\ContactFieldPreferences;

final class BusinessSettingsPresenter
{
    public function __construct(
        private readonly BusinessRepository $businesses,
        private readonly BusinessAddressBook $addresses,
        private readonly BusinessLinkList $links,
        private readonly BusinessSchedule $schedule,
        private readonly BookingPageSettings $bookingPages,
        private readonly BookingPolicySettings $bookingPolicies,
        private readonly BookingRulesAllowance $bookingRules,
        private readonly PhoneBook $phones,
        private readonly BusinessLogo $logo,
    ) {}

    /**
     * @throws BusinessNotFound
     */
    public function describe(string $businessId): BusinessSettingsData
    {
        $business = $this->businesses->findById($businessId);

        if (! $this->bookingRules->includesBookingRules($businessId)) {
            return $this->describeWith(
                $business,
                $this->bookingPolicies->platformDefaults(),
                $this->bookingPolicies->platformDefaultContactFields(),
            );
        }

        return $this->describeWith(
            $business,
            $this->bookingPolicies->forBusiness($businessId),
            $this->bookingPolicies->contactFieldsFor($businessId),
        );
    }

    private function describeWith(
        Business $business,
        BookingPolicySnapshot $bookingPolicy,
        ContactFieldPreferences $contactFields,
    ): BusinessSettingsData {
        return new BusinessSettingsData(
            id: $business->id,
            name: $business->name(),
            slug: $business->slug(),
            industryId: $business->industryId(),
            timezone: $business->timezone(),
            about: $business->about(),
            contactEmail: $business->contactEmail(),
            currencyCode: $business->currency(),
            logoUrl: $this->logo->urlFor($business->id),
            phone: $this->phones->forBusiness($business->id),
            address: $this->addresses->forBusiness($business->id),
            schedule: $this->schedule->forBusiness($business->id),
            links: $this->links->forBusiness($business->id),
            bookingPage: $this->bookingPages->forBusiness($business->id),
            bookingPolicy: $bookingPolicy,
            contactFields: $contactFields,
        );
    }
}
