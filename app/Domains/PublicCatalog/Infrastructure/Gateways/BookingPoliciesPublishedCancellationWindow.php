<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\BookingPolicies\Contracts\BookingPolicyRepository;
use App\Domains\BookingPolicies\Entities\BookingPolicy;
use App\Domains\PublicCatalog\Contracts\BookingRulesAllowance;
use App\Domains\PublicCatalog\Contracts\PublishedCancellationWindow;
use App\Domains\PublicCatalog\ValueObjects\PublicCancellationWindow;

final class BookingPoliciesPublishedCancellationWindow implements PublishedCancellationWindow
{
    public function __construct(
        private readonly BookingPolicyRepository $policies,
        private readonly BookingRulesAllowance $allowance,
    ) {}

    public function forBusiness(string $businessId): PublicCancellationWindow
    {
        $minutes = $this->effectiveMinutesFor($businessId);

        if ($minutes === null) {
            return PublicCancellationWindow::notAllowed();
        }

        return PublicCancellationWindow::ofMinutes($minutes);
    }

    private function effectiveMinutesFor(string $businessId): ?int
    {
        if (! $this->allowance->includesBookingRules($businessId)) {
            return BookingPolicy::DEFAULT_CANCELLATION_WINDOW_MINUTES;
        }

        $policy = $this->policies->findForBusiness($businessId);

        if ($policy === null) {
            return BookingPolicy::DEFAULT_CANCELLATION_WINDOW_MINUTES;
        }

        return $policy->cancellationWindow()->minutes;
    }
}
