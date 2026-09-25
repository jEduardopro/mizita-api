<?php

declare(strict_types=1);

use App\Domains\Integrations\ValueObjects\AppointmentLifecycle;
use App\Domains\Integrations\ValueObjects\AppointmentSnapshot;
use App\Domains\Integrations\ValueObjects\CalendarEventDraft;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->appointmentId = '01930000-0000-7000-8000-0000000000f1';
    $this->mizitaLink = 'https://mizita.test/dashboard/agenda';

    $this->snapshot = fn (
        AppointmentLifecycle $lifecycle = AppointmentLifecycle::Active,
        string $serviceName = 'Haircut',
        string $customerName = 'Ada Lovelace',
    ) => new AppointmentSnapshot(
        appointmentId: $this->appointmentId,
        businessId: FakeBusinessContext::BUSINESS_ID,
        staffMemberId: '01930000-0000-7000-8000-0000000000d1',
        startsAt: new DateTimeImmutable('2026-03-10T09:00:00+00:00'),
        endsAt: new DateTimeImmutable('2026-03-10T09:45:00+00:00'),
        serviceName: $serviceName,
        customerName: $customerName,
        referenceCode: 'MZ7K2Q',
        timezone: 'Europe/Madrid',
        lifecycle: $lifecycle,
    );
});

describe('drafting the calendar event of an appointment', function () {
    it('drafts an event for the appointment it describes', function () {
        $draft = ($this->snapshot)()->draftFor($this->mizitaLink);

        expect($draft)->toBeInstanceOf(CalendarEventDraft::class)
            ->and($draft->appointmentId)->toBe($this->appointmentId);
    });

    it('titles the event with the service and the customer', function () {
        expect(($this->snapshot)()->draftFor($this->mizitaLink)->title)->toBe('Haircut — Ada Lovelace');
    });

    it('describes the event with the reference code and the link back to Mizita, and nothing else', function () {
        expect(($this->snapshot)()->draftFor($this->mizitaLink)->description)
            ->toBe("Ref. MZ7K2Q\nhttps://mizita.test/dashboard/agenda");
    });

    it('runs the event between the instants the appointment is booked for', function () {
        $draft = ($this->snapshot)()->draftFor($this->mizitaLink);

        expect($draft->startsAt->format(DATE_ATOM))->toBe('2026-03-10T09:00:00+00:00')
            ->and($draft->endsAt->format(DATE_ATOM))->toBe('2026-03-10T09:45:00+00:00');
    });

    it('carries the zone of the business so the calendar shows local time', function () {
        expect(($this->snapshot)()->draftFor($this->mizitaLink)->timezone)->toBe('Europe/Madrid');
    });

    it('keeps accents and non-latin letters in the title intact', function () {
        $draft = ($this->snapshot)(serviceName: 'Coloración', customerName: 'José Núñez 李')->draftFor($this->mizitaLink);

        expect($draft->title)->toBe('Coloración — José Núñez 李');
    });

    it('carries whatever link it is handed, verbatim', function () {
        expect(($this->snapshot)()->draftFor('https://app.mizita.example/a?x=1&y=2')->description)
            ->toBe("Ref. MZ7K2Q\nhttps://app.mizita.example/a?x=1&y=2");
    });
});

describe('telling whether the appointment is still active', function () {
    it('is active while the appointment stands', function () {
        expect(($this->snapshot)(AppointmentLifecycle::Active)->isActive())->toBeTrue();
    });

    it('is not active once the appointment is gone', function (AppointmentLifecycle $lifecycle) {
        expect(($this->snapshot)($lifecycle)->isActive())->toBeFalse();
    })->with([
        'cancelled' => [AppointmentLifecycle::Cancelled],
        'deleted' => [AppointmentLifecycle::Deleted],
    ]);
});
