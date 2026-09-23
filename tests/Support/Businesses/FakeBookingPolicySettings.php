<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use App\Domains\Businesses\Contracts\BookingPolicySettings;
use App\Domains\Businesses\ValueObjects\BookingPolicyPreferences;
use App\Domains\Businesses\ValueObjects\BookingPolicySnapshot;
use App\Domains\Businesses\ValueObjects\ContactFieldPreferences;
use Throwable;

final class FakeBookingPolicySettings implements BookingPolicySettings
{
    /**
     * @var array<string, BookingPolicySnapshot>
     */
    private array $policies = [];

    /**
     * @var array<string, ContactFieldPreferences>
     */
    private array $contactFields = [];

    private ?Throwable $applyFailure = null;

    private ?Throwable $contactFieldsFailure = null;

    /**
     * @var list<array{businessId: string, preferences: BookingPolicyPreferences}>
     */
    public array $applications = [];

    /**
     * @var list<array{businessId: string, preferences: ContactFieldPreferences}>
     */
    public array $contactFieldApplications = [];

    /**
     * @var list<string>
     */
    public array $reads = [];

    /**
     * @var list<string>
     */
    public array $contactFieldReads = [];

    public function store(string $businessId, BookingPolicySnapshot $policy): self
    {
        $this->policies[$businessId] = $policy;

        return $this;
    }

    public function storeContactFields(string $businessId, ContactFieldPreferences $preferences): self
    {
        $this->contactFields[$businessId] = $preferences;

        return $this;
    }

    public function failingOnApply(Throwable $failure): self
    {
        $this->applyFailure = $failure;

        return $this;
    }

    public function failingOnApplyContactFields(Throwable $failure): self
    {
        $this->contactFieldsFailure = $failure;

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

    public function contactFieldsFor(string $businessId): ContactFieldPreferences
    {
        $this->contactFieldReads[] = $businessId;

        return $this->contactFields[$businessId] ?? SettingsFixtures::contactFields();
    }

    public function applyContactFieldsTo(string $businessId, ContactFieldPreferences $preferences): void
    {
        if ($this->contactFieldsFailure !== null) {
            throw $this->contactFieldsFailure;
        }

        $this->contactFieldApplications[] = ['businessId' => $businessId, 'preferences' => $preferences];

        $this->contactFields[$businessId] = $preferences;
    }
}
