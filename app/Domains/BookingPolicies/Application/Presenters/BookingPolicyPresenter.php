<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\Application\Presenters;

use App\Domains\BookingPolicies\Application\Dtos\BookingPolicyData;
use App\Domains\BookingPolicies\Entities\BookingPolicy;

final class BookingPolicyPresenter
{
    public function describe(BookingPolicy $policy): BookingPolicyData
    {
        return new BookingPolicyData(
            id: $policy->id,
            leadTimeMinutes: $policy->leadTime()->minutes,
            bookingWindowMinutes: $policy->bookingWindow()->minutes(),
            bookingWindowUnlimited: $policy->bookingWindow()->isUnlimited(),
            slotGranularityMinutes: $policy->slotGranularity()->minutes,
            cancellationWindowMinutes: $policy->cancellationWindow()->minutes,
            cancellationAllowed: $policy->cancellationWindow()->isAllowed(),
            policyMessage: $policy->policyMessage()->toString(),
            displayOnBookingPage: $policy->isDisplayedOnBookingPage(),
        );
    }
}
