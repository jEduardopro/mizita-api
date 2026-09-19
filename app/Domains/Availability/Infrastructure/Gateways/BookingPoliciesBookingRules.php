<?php

declare(strict_types=1);

namespace App\Domains\Availability\Infrastructure\Gateways;

use App\Domains\Availability\Contracts\BookingRules;
use App\Domains\Availability\ValueObjects\SlotRules;
use App\Domains\BookingPolicies\Contracts\BookingPolicyRepository;
use App\Domains\BookingPolicies\Entities\BookingPolicy;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;

final class BookingPoliciesBookingRules implements BookingRules
{
    public function __construct(
        private readonly BookingPolicyRepository $policies,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {}

    public function forBusiness(string $businessId): SlotRules
    {
        $policy = $this->policies->findForBusiness($businessId) ?? $this->defaultsFor($businessId);

        return new SlotRules(
            leadTimeMinutes: $policy->leadTime()->minutes,
            bookingWindowMinutes: $policy->bookingWindow()->minutes(),
            slotGranularityMinutes: $policy->slotGranularity()->minutes,
        );
    }

    private function defaultsFor(string $businessId): BookingPolicy
    {
        return BookingPolicy::withDefaults(
            id: $this->ids->next(),
            businessId: $businessId,
            now: $this->clock->now(),
        );
    }
}
