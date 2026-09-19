<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\Infrastructure;

use App\Domains\BookingPolicies\Contracts\BookingPolicyRepository;
use App\Domains\BookingPolicies\Contracts\CurrentBookingPolicy;
use App\Domains\BookingPolicies\Entities\BookingPolicy;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;

final class ProvisionedCurrentBookingPolicy implements CurrentBookingPolicy
{
    public function __construct(
        private readonly BookingPolicyRepository $policies,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {}

    public function forBusiness(string $businessId): BookingPolicy
    {
        $policy = $this->policies->findForBusiness($businessId);

        if ($policy !== null) {
            return $policy;
        }

        $policy = BookingPolicy::withDefaults(
            id: $this->ids->next(),
            businessId: $businessId,
            now: $this->clock->now(),
        );

        $this->policies->save($policy);

        return $policy;
    }
}
