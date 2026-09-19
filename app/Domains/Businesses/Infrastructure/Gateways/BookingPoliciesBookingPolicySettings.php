<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Gateways;

use App\Domains\BookingPolicies\Application\Presenters\BookingPolicyPresenter;
use App\Domains\BookingPolicies\Contracts\BookingPolicyRepository;
use App\Domains\BookingPolicies\Contracts\CurrentBookingPolicy;
use App\Domains\BookingPolicies\Entities\BookingPolicy;
use App\Domains\BookingPolicies\ValueObjects\BookingWindow;
use App\Domains\BookingPolicies\ValueObjects\CancellationWindow;
use App\Domains\BookingPolicies\ValueObjects\LeadTime;
use App\Domains\BookingPolicies\ValueObjects\PolicyMessage;
use App\Domains\BookingPolicies\ValueObjects\SlotGranularity;
use App\Domains\Businesses\Contracts\BookingPolicySettings;
use App\Domains\Businesses\ValueObjects\BookingPolicyPreferences;
use App\Domains\Businesses\ValueObjects\BookingPolicySnapshot;

final class BookingPoliciesBookingPolicySettings implements BookingPolicySettings
{
    public function __construct(
        private readonly CurrentBookingPolicy $policies,
        private readonly BookingPolicyPresenter $presenter,
        private readonly BookingPolicyRepository $repository,
    ) {}

    public function forBusiness(string $businessId): BookingPolicySnapshot
    {
        $policy = $this->presenter->describe($this->policies->forBusiness($businessId));

        return new BookingPolicySnapshot(
            leadTimeMinutes: $policy->leadTimeMinutes,
            bookingWindowMinutes: $policy->bookingWindowMinutes,
            slotGranularityMinutes: $policy->slotGranularityMinutes,
            cancellationWindowMinutes: $policy->cancellationWindowMinutes,
            policyMessage: $policy->policyMessage,
            displayOnBookingPage: $policy->displayOnBookingPage,
        );
    }

    public function applyTo(string $businessId, BookingPolicyPreferences $preferences): void
    {
        $policy = $this->policies->forBusiness($businessId);

        $policy->revise(
            leadTime: LeadTime::ofMinutes($preferences->leadTimeMinutes),
            bookingWindow: self::bookingWindowOf($preferences->bookingWindowMinutes),
            slotGranularity: SlotGranularity::ofMinutes($preferences->slotGranularityMinutes),
            cancellationWindow: self::cancellationWindowOf($preferences->cancellationWindowMinutes),
            policyMessage: PolicyMessage::fromString($preferences->policyMessage),
        );

        self::applyVisibility($policy, $preferences->displayOnBookingPage);

        $this->repository->save($policy);
    }

    private static function bookingWindowOf(?int $minutes): BookingWindow
    {
        if ($minutes === null) {
            return BookingWindow::unlimited();
        }

        return BookingWindow::ofMinutes($minutes);
    }

    private static function cancellationWindowOf(?int $minutes): CancellationWindow
    {
        if ($minutes === null) {
            return CancellationWindow::notAllowed();
        }

        return CancellationWindow::ofMinutes($minutes);
    }

    private static function applyVisibility(BookingPolicy $policy, bool $displayed): void
    {
        if ($displayed) {
            $policy->displayOnBookingPage();

            return;
        }

        $policy->hideFromBookingPage();
    }
}
