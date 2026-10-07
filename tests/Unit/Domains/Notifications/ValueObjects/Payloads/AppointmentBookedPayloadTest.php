<?php

declare(strict_types=1);

use App\Domains\Notifications\ValueObjects\NotificationSubjectType;
use App\Domains\Notifications\ValueObjects\NotificationType;
use App\Domains\Notifications\ValueObjects\NotifiedAppointment;
use App\Domains\Notifications\ValueObjects\Payloads\AppointmentBookedPayload;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

const BOOKED_PAYLOAD_MADRID = 'Europe/Madrid';

function bookedPayloadAt(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): AppointmentBookedPayload
{
    return new AppointmentBookedPayload(
        new NotifiedAppointment(
            appointmentId: NotificationsFixtures::APPOINTMENT_ID,
            startsAt: $startsAt,
            endsAt: $endsAt,
            serviceName: NotificationsFixtures::SERVICE_NAME,
            referenceCode: NotificationsFixtures::REFERENCE_CODE,
        ),
        NotificationsFixtures::customer(),
    );
}

function inMadrid(string $utcInstant): DateTimeImmutable
{
    return (new DateTimeImmutable($utcInstant))->setTimezone(new DateTimeZone(BOOKED_PAYLOAD_MADRID));
}

describe('what it is about', function () {
    beforeEach(function () {
        $this->payload = NotificationsFixtures::bookingPayload();
    });

    it('is a booking', function () {
        expect($this->payload->type())->toBe(NotificationType::AppointmentBooked);
    });

    it('is about the appointment, by uuid', function () {
        expect($this->payload->subject()->type)->toBe(NotificationSubjectType::Appointment)
            ->and($this->payload->subject()->id)->toBe(NotificationsFixtures::APPOINTMENT_ID);
    });

    it('keys idempotency on the appointment, whatever the event uuid', function (string $eventId) {
        expect($this->payload->idempotencyKey($eventId))->toBe('appointment_booked:'.NotificationsFixtures::APPOINTMENT_ID);
    })->with([
        'an event' => NotificationsFixtures::EVENT_ID,
        'another event' => NotificationsFixtures::NEXT_EVENT_ID,
    ]);

    it('collapses on the same key it is idempotent on', function () {
        expect($this->payload->collapseKey(NotificationsFixtures::EVENT_ID))
            ->toBe($this->payload->idempotencyKey(NotificationsFixtures::EVENT_ID));
    });
});

describe('writing the snapshot', function () {
    it('writes the appointment and the customer under their uuids, with instants in atom', function () {
        expect(NotificationsFixtures::bookingPayload()->toArray())->toBe(NotificationsFixtures::bookingSnapshot());
    });

    it('writes an instant held in a local zone as the same instant in utc', function () {
        $payload = bookedPayloadAt(inMadrid('2026-03-12T10:00:00+00:00'), inMadrid('2026-03-12T10:45:00+00:00'));

        expect($payload->toArray()['appointment']['starts_at'])->toBe('2026-03-12T10:00:00+00:00')
            ->and($payload->toArray()['appointment']['ends_at'])->toBe('2026-03-12T10:45:00+00:00');
    });

    it('writes the first appointment after the spring forward gap in utc', function () {
        $payload = bookedPayloadAt(inMadrid('2026-03-29T01:00:00+00:00'), inMadrid('2026-03-29T01:45:00+00:00'));

        expect($payload->toArray()['appointment']['starts_at'])->toBe('2026-03-29T01:00:00+00:00')
            ->and($payload->toArray()['appointment']['ends_at'])->toBe('2026-03-29T01:45:00+00:00');
    });

    it('writes the two local half past ones of the fall back day as two different instants', function () {
        $first = bookedPayloadAt(inMadrid('2026-10-25T00:30:00+00:00'), inMadrid('2026-10-25T01:15:00+00:00'));
        $second = bookedPayloadAt(inMadrid('2026-10-25T01:30:00+00:00'), inMadrid('2026-10-25T02:15:00+00:00'));

        expect($first->appointment->startsAt->format('H:i'))->toBe($second->appointment->startsAt->format('H:i'))
            ->and($first->toArray()['appointment']['starts_at'])->toBe('2026-10-25T00:30:00+00:00')
            ->and($second->toArray()['appointment']['starts_at'])->toBe('2026-10-25T01:30:00+00:00');
    });
});

describe('reading the snapshot back', function () {
    it('survives a round trip', function () {
        $payload = NotificationsFixtures::bookingPayload();

        expect(AppointmentBookedPayload::fromArray($payload->toArray()))->toEqual($payload);
    });

    it('reads every field back under its uuid', function () {
        $payload = AppointmentBookedPayload::fromArray(NotificationsFixtures::bookingSnapshot());

        expect($payload->appointment->appointmentId)->toBe(NotificationsFixtures::APPOINTMENT_ID)
            ->and($payload->appointment->serviceName)->toBe(NotificationsFixtures::SERVICE_NAME)
            ->and($payload->appointment->referenceCode)->toBe(NotificationsFixtures::REFERENCE_CODE)
            ->and($payload->customer->customerId)->toBe(NotificationsFixtures::CUSTOMER_ID)
            ->and($payload->customer->name)->toBe(NotificationsFixtures::CUSTOMER_NAME);
    });

    it('reads the instants back in utc', function () {
        $payload = AppointmentBookedPayload::fromArray(NotificationsFixtures::bookingSnapshot());

        expect($payload->appointment->startsAt)->toEqual(new DateTimeImmutable(NotificationsFixtures::STARTS_AT))
            ->and($payload->appointment->startsAt->getTimezone()->getName())->toBe('UTC')
            ->and($payload->appointment->endsAt)->toEqual(new DateTimeImmutable(NotificationsFixtures::ENDS_AT))
            ->and($payload->appointment->endsAt->getTimezone()->getName())->toBe('UTC');
    });

    it('reads a stored local offset back as the same instant in utc', function () {
        $snapshot = NotificationsFixtures::bookingSnapshot();
        $snapshot['appointment']['starts_at'] = '2026-10-25T02:30:00+01:00';

        $startsAt = AppointmentBookedPayload::fromArray($snapshot)->appointment->startsAt;

        expect($startsAt->format(DATE_ATOM))->toBe('2026-10-25T01:30:00+00:00');
    });

    it('reads a missing text field as empty rather than failing', function (string $section, string $field) {
        $snapshot = NotificationsFixtures::bookingSnapshot();
        unset($snapshot[$section][$field]);

        expect(AppointmentBookedPayload::fromArray($snapshot)->toArray()[$section][$field])->toBe('');
    })->with([
        'the appointment id' => ['appointment', 'id'],
        'the service name' => ['appointment', 'service_name'],
        'the reference code' => ['appointment', 'reference_code'],
        'the customer id' => ['customer', 'id'],
        'the customer name' => ['customer', 'name'],
    ]);

    it('reads a missing or malformed customer section as an empty customer', function (mixed $customer) {
        $snapshot = NotificationsFixtures::bookingSnapshot();
        $snapshot['customer'] = $customer;

        expect(AppointmentBookedPayload::fromArray($snapshot)->toArray()['customer'])->toBe(['id' => '', 'name' => '']);
    })->with([
        'an empty section' => [[]],
        'a string' => ['José'],
        'null' => [null],
    ]);

    it('reads a text field that is not text as empty', function (mixed $name) {
        $snapshot = NotificationsFixtures::bookingSnapshot();
        $snapshot['customer']['name'] = $name;

        expect(AppointmentBookedPayload::fromArray($snapshot)->customer->name)->toBe('');
    })->with([
        'a list' => [['José']],
        'null' => [null],
    ]);

    it('reads a scalar text field as its string', function () {
        $snapshot = NotificationsFixtures::bookingSnapshot();
        $snapshot['appointment']['reference_code'] = 123456;

        expect(AppointmentBookedPayload::fromArray($snapshot)->appointment->referenceCode)->toBe('123456');
    });

    it('refuses an instant that is missing or not in atom', function (string $field, mixed $instant) {
        $snapshot = NotificationsFixtures::bookingSnapshot();
        $snapshot['appointment'][$field] = $instant;

        expect(fn () => AppointmentBookedPayload::fromArray($snapshot))->toThrow(UnexpectedValueException::class);
    })->with([
        'an empty start' => ['starts_at', ''],
        'a start with no offset' => ['starts_at', '2026-03-12 10:00:00'],
        'a start that is a word' => ['starts_at', 'tomorrow'],
        'a start that is a list' => ['starts_at', ['2026-03-12T10:00:00+00:00']],
        'an empty end' => ['ends_at', ''],
        'an end with no offset' => ['ends_at', '2026-03-12T10:45:00'],
    ]);

    it('refuses a snapshot with no appointment at all', function () {
        $snapshot = NotificationsFixtures::bookingSnapshot();
        unset($snapshot['appointment']);

        expect(fn () => AppointmentBookedPayload::fromArray($snapshot))->toThrow(UnexpectedValueException::class);
    });
});
