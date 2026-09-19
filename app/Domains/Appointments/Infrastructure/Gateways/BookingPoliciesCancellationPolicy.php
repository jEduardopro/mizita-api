<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Gateways;

use App\Domains\Appointments\Contracts\CancellationPolicy;
use App\Domains\Appointments\ValueObjects\CancellationRule;
use App\Domains\BookingPolicies\Contracts\BookingPolicyRepository;
use App\Domains\BookingPolicies\Entities\BookingPolicy;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;

final class BookingPoliciesCancellationPolicy implements CancellationPolicy
{
    public function __construct(
        private readonly BookingPolicyRepository $policies,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {}

    public function forBusiness(string $businessId): CancellationRule
    {
        $policy = $this->policies->findForBusiness($businessId) ?? $this->defaultsFor($businessId);
        $minutes = $policy->cancellationWindow()->minutes;

        if ($minutes === null) {
            return CancellationRule::notAllowed();
        }

        return CancellationRule::ofMinutes($minutes);
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
