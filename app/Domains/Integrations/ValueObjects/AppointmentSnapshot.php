<?php

declare(strict_types=1);

namespace App\Domains\Integrations\ValueObjects;

use DateTimeImmutable;

final readonly class AppointmentSnapshot
{
    private const TITLE_SEPARATOR = ' — ';

    private const REFERENCE_LABEL = 'Ref. ';

    public function __construct(
        public string $appointmentId,
        public string $businessId,
        public string $staffMemberId,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $serviceName,
        public string $customerName,
        public string $referenceCode,
        public string $timezone,
        private AppointmentLifecycle $lifecycle,
    ) {}

    public function isActive(): bool
    {
        return $this->lifecycle === AppointmentLifecycle::Active;
    }

    public function draftFor(string $mizitaLink): CalendarEventDraft
    {
        return new CalendarEventDraft(
            appointmentId: $this->appointmentId,
            title: $this->serviceName.self::TITLE_SEPARATOR.$this->customerName,
            description: self::REFERENCE_LABEL.$this->referenceCode."\n".$mizitaLink,
            startsAt: $this->startsAt,
            endsAt: $this->endsAt,
            timezone: $this->timezone,
        );
    }
}
