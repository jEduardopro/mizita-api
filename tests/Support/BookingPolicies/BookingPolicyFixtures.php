<?php

declare(strict_types=1);

namespace Tests\Support\BookingPolicies;

use App\Domains\BookingPolicies\Entities\BookingPolicy;
use App\Domains\BookingPolicies\ValueObjects\BookingWindow;
use App\Domains\BookingPolicies\ValueObjects\CancellationWindow;
use App\Domains\BookingPolicies\ValueObjects\ContactFieldRequirement;
use App\Domains\BookingPolicies\ValueObjects\ContactFields;
use App\Domains\BookingPolicies\ValueObjects\LeadTime;
use App\Domains\BookingPolicies\ValueObjects\PolicyMessage;
use App\Domains\BookingPolicies\ValueObjects\SlotGranularity;
use DateTimeImmutable;
use Tests\Support\FakeBusinessContext;

final class BookingPolicyFixtures
{
    public const NOW = '2026-01-01T12:00:00+00:00';

    public const POLICY_ID = '01930000-0000-7000-8000-0000000000a1';

    public const OTHER_POLICY_ID = '01930000-0000-7000-8000-0000000000a2';

    public const GENERATED_POLICY_ID = '01930000-0000-7000-8000-0000000000a9';

    public const OTHER_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b2';

    public const POLICY_MESSAGE = 'Cancela con cuatro horas de antelación.';

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    public static function policy(
        string $id = self::POLICY_ID,
        string $businessId = FakeBusinessContext::BUSINESS_ID,
        int $leadTimeMinutes = 60,
        ?int $bookingWindowMinutes = 43200,
        int $slotGranularityMinutes = 30,
        ?int $cancellationWindowMinutes = 240,
        ?string $policyMessage = self::POLICY_MESSAGE,
        bool $displayedOnBookingPage = true,
        ?ContactFields $contactFields = null,
        ?DateTimeImmutable $createdAt = null,
    ): BookingPolicy {
        return BookingPolicy::restore(
            id: $id,
            businessId: $businessId,
            leadTime: LeadTime::restore($leadTimeMinutes),
            bookingWindow: BookingWindow::restore($bookingWindowMinutes),
            slotGranularity: SlotGranularity::restore($slotGranularityMinutes),
            cancellationWindow: CancellationWindow::restore($cancellationWindowMinutes),
            policyMessage: PolicyMessage::restore($policyMessage),
            displayedOnBookingPage: $displayedOnBookingPage,
            contactFields: $contactFields ?? self::contactFields(),
            createdAt: $createdAt ?? self::now(),
        );
    }

    public static function contactFields(
        ContactFieldRequirement $phone = ContactFieldRequirement::Hidden,
        ContactFieldRequirement $email = ContactFieldRequirement::Required,
        ContactFieldRequirement $address = ContactFieldRequirement::Optional,
    ): ContactFields {
        return new ContactFields($phone, $email, $address);
    }
}
