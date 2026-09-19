<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use App\Domains\Businesses\Contracts\BookingPolicySettings;
use App\Domains\Businesses\ValueObjects\BookingPolicyPreferences;
use App\Domains\Businesses\ValueObjects\BookingPolicySnapshot;
use Throwable;

final class FakeBookingPolicySettings implements BookingPolicySettings
{
    /**
     * @var array<string, BookingPolicySnapshot>
     */
    private array $policies = [];

    private ?Throwable $applyFailure = null;

    /**
     * @var list<array{businessId: string, preferences: BookingPolicyPreferences}>
     */
    public array $applications = [];

    /**
     * @var list<string>
     */
    public array $reads = [];

    public function store(string $businessId, BookingPolicySnapshot $policy): self
    {
        $this->policies[$businessId] = $policy;

        return $this;
    }

    public function failingOnApply(Throwable $failure): self
    {
        $this->applyFailure = $failure;

        return $this;
    }

    public function forBusiness(string $businessId): BookingPolicySnapshot
    {
        $this->reads[] = $businessId;

        return $this->policies[$businessId] ?? SettingsFixtures::bookingPolicy();
    }

    public function applyTo(string $businessId, BookingPolicyPreferences $preferences): void
    {
        if ($this->applyFailure !== null) {
            throw $this->applyFailure;
        }

        $this->applications[] = ['businessId' => $businessId, 'preferences' => $preferences];

        $this->policies[$businessId] = new BookingPolicySnapshot(
            leadTimeMinutes: $preferences->leadTimeMinutes,
            bookingWindowMinutes: $preferences->bookingWindowMinutes,
            slotGranularityMinutes: $preferences->slotGranularityMinutes,
            cancellationWindowMinutes: $preferences->cancellationWindowMinutes,
            policyMessage: $preferences->policyMessage,
            displayOnBookingPage: $preferences->displayOnBookingPage,
        );
    }
}
