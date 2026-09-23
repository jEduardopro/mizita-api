<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\Entities;

use App\Domains\BookingPolicies\ValueObjects\BookingWindow;
use App\Domains\BookingPolicies\ValueObjects\CancellationWindow;
use App\Domains\BookingPolicies\ValueObjects\ContactFields;
use App\Domains\BookingPolicies\ValueObjects\LeadTime;
use App\Domains\BookingPolicies\ValueObjects\PolicyMessage;
use App\Domains\BookingPolicies\ValueObjects\SlotGranularity;
use DateTimeImmutable;

final class BookingPolicy
{
    public const DEFAULT_LEAD_TIME_MINUTES = 0;

    public const DEFAULT_BOOKING_WINDOW_MINUTES = null;

    public const DEFAULT_SLOT_GRANULARITY_MINUTES = 15;

    public const DEFAULT_CANCELLATION_WINDOW_MINUTES = 120;

    public const DEFAULT_DISPLAY_ON_BOOKING_PAGE = false;

    private function __construct(
        public readonly string $id,
        public readonly string $businessId,
        private LeadTime $leadTime,
        private BookingWindow $bookingWindow,
        private SlotGranularity $slotGranularity,
        private CancellationWindow $cancellationWindow,
        private PolicyMessage $policyMessage,
        private bool $displayedOnBookingPage,
        private ContactFields $contactFields,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        string $id,
        string $businessId,
        LeadTime $leadTime,
        BookingWindow $bookingWindow,
        SlotGranularity $slotGranularity,
        CancellationWindow $cancellationWindow,
        PolicyMessage $policyMessage,
        bool $displayedOnBookingPage,
        ContactFields $contactFields,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            leadTime: $leadTime,
            bookingWindow: $bookingWindow,
            slotGranularity: $slotGranularity,
            cancellationWindow: $cancellationWindow,
            policyMessage: $policyMessage,
            displayedOnBookingPage: $displayedOnBookingPage,
            contactFields: $contactFields,
            createdAt: $now,
        );
    }

    public static function withDefaults(string $id, string $businessId, DateTimeImmutable $now): self
    {
        return self::create(
            id: $id,
            businessId: $businessId,
            leadTime: LeadTime::restore(self::DEFAULT_LEAD_TIME_MINUTES),
            bookingWindow: BookingWindow::unlimited(),
            slotGranularity: SlotGranularity::restore(self::DEFAULT_SLOT_GRANULARITY_MINUTES),
            cancellationWindow: CancellationWindow::restore(self::DEFAULT_CANCELLATION_WINDOW_MINUTES),
            policyMessage: PolicyMessage::none(),
            displayedOnBookingPage: self::DEFAULT_DISPLAY_ON_BOOKING_PAGE,
            contactFields: ContactFields::defaults(),
            now: $now,
        );
    }

    public static function restore(
        string $id,
        string $businessId,
        LeadTime $leadTime,
        BookingWindow $bookingWindow,
        SlotGranularity $slotGranularity,
        CancellationWindow $cancellationWindow,
        PolicyMessage $policyMessage,
        bool $displayedOnBookingPage,
        ContactFields $contactFields,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            leadTime: $leadTime,
            bookingWindow: $bookingWindow,
            slotGranularity: $slotGranularity,
            cancellationWindow: $cancellationWindow,
            policyMessage: $policyMessage,
            displayedOnBookingPage: $displayedOnBookingPage,
            contactFields: $contactFields,
            createdAt: $createdAt,
        );
    }

    public function revise(
        LeadTime $leadTime,
        BookingWindow $bookingWindow,
        SlotGranularity $slotGranularity,
        CancellationWindow $cancellationWindow,
        PolicyMessage $policyMessage,
    ): void {
        $this->leadTime = $leadTime;
        $this->bookingWindow = $bookingWindow;
        $this->slotGranularity = $slotGranularity;
        $this->cancellationWindow = $cancellationWindow;
        $this->policyMessage = $policyMessage;
    }

    public function reviseContactFields(ContactFields $contactFields): void
    {
        $this->contactFields = $contactFields;
    }

    public function displayOnBookingPage(): void
    {
        $this->displayedOnBookingPage = true;
    }

    public function hideFromBookingPage(): void
    {
        $this->displayedOnBookingPage = false;
    }

    public function isDisplayedOnBookingPage(): bool
    {
        return $this->displayedOnBookingPage;
    }

    public function leadTime(): LeadTime
    {
        return $this->leadTime;
    }

    public function bookingWindow(): BookingWindow
    {
        return $this->bookingWindow;
    }

    public function slotGranularity(): SlotGranularity
    {
        return $this->slotGranularity;
    }

    public function cancellationWindow(): CancellationWindow
    {
        return $this->cancellationWindow;
    }

    public function policyMessage(): PolicyMessage
    {
        return $this->policyMessage;
    }

    public function contactFields(): ContactFields
    {
        return $this->contactFields;
    }
}
