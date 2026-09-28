<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\BookingPolicies\Contracts\BookingPolicyRepository;
use App\Domains\PublicCatalog\Contracts\BookingRulesAllowance;
use App\Domains\PublicCatalog\Contracts\PublishedBookingPolicy;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingPolicy;

final class BookingPoliciesPublishedBookingPolicy implements PublishedBookingPolicy
{
    public function __construct(
        private readonly BookingPolicyRepository $policies,
        private readonly BookingRulesAllowance $allowance,
    ) {}

    public function forBusiness(string $businessId): ?PublicBookingPolicy
    {
        if (! $this->allowance->includesBookingRules($businessId)) {
            return null;
        }

        $policy = $this->policies->findForBusiness($businessId);

        if ($policy === null || ! $policy->isDisplayedOnBookingPage()) {
            return null;
        }

        $message = $policy->policyMessage()->toString();

        if ($message === null) {
            return null;
        }

        return new PublicBookingPolicy($message);
    }
}
