<?php

declare(strict_types=1);

namespace App\Domains\Notifications\ValueObjects\Payloads;

use App\Domains\Notifications\ValueObjects\NotificationSubject;
use App\Domains\Notifications\ValueObjects\NotificationSubjectType;
use App\Domains\Notifications\ValueObjects\NotificationType;
use App\Domains\Notifications\ValueObjects\NotifiedAppointment;
use App\Domains\Notifications\ValueObjects\NotifiedCustomer;
use DateTimeImmutable;
use DateTimeZone;

final readonly class AppointmentBookedPayload implements NotificationPayload
{
    private const APPOINTMENT = 'appointment';

    private const CUSTOMER = 'customer';

    private const WIRE_TIMEZONE = 'UTC';

    public function __construct(
        public NotifiedAppointment $appointment,
        public NotifiedCustomer $customer,
    ) {}

    /**
     * @param  array<array-key, mixed>  $snapshot
     */
    public static function fromArray(array $snapshot): self
    {
        $appointment = SnapshotReader::of($snapshot)->section(self::APPOINTMENT);
        $customer = SnapshotReader::of($snapshot)->section(self::CUSTOMER);

        return new self(
            appointment: new NotifiedAppointment(
                appointmentId: $appointment->text('id'),
                startsAt: $appointment->instant('starts_at'),
                endsAt: $appointment->instant('ends_at'),
                serviceName: $appointment->text('service_name'),
                referenceCode: $appointment->text('reference_code'),
            ),
            customer: new NotifiedCustomer(
                customerId: $customer->text('id'),
                name: $customer->text('name'),
            ),
        );
    }

    public function type(): NotificationType
    {
        return NotificationType::AppointmentBooked;
    }

    public function subject(): NotificationSubject
    {
        return new NotificationSubject(NotificationSubjectType::Appointment, $this->appointment->appointmentId);
    }

    public function idempotencyKey(string $eventId): string
    {
        return $this->type()->keyFor($this->appointment->appointmentId);
    }

    public function collapseKey(string $eventId): string
    {
        return $this->idempotencyKey($eventId);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function toArray(): array
    {
        return [
            self::APPOINTMENT => [
                'id' => $this->appointment->appointmentId,
                'starts_at' => self::atomOf($this->appointment->startsAt),
                'ends_at' => self::atomOf($this->appointment->endsAt),
                'service_name' => $this->appointment->serviceName,
                'reference_code' => $this->appointment->referenceCode,
            ],
            self::CUSTOMER => [
                'id' => $this->customer->customerId,
                'name' => $this->customer->name,
            ],
        ];
    }

    private static function atomOf(DateTimeImmutable $instant): string
    {
        return $instant->setTimezone(new DateTimeZone(self::WIRE_TIMEZONE))->format(DATE_ATOM);
    }
}
