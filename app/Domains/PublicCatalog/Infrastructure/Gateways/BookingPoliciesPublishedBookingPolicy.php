<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\BookingPolicies\Contracts\BookingPolicyRepository;
use App\Domains\PublicCatalog\Contracts\PublishedBookingPolicy;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingPolicy;

final class BookingPoliciesPublishedBookingPolicy implements PublishedBookingPolicy
{
    public function __construct(
        private readonly BookingPolicyRepository $policies,
    ) {}

    public function forBusiness(string $businessId): ?PublicBookingPolicy
    {
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
