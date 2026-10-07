<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Eloquent\Factories;

use App\Domains\Appointments\Infrastructure\Eloquent\Models\AppointmentModel;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Notifications\Infrastructure\Eloquent\Models\NotificationEventModel;
use App\Domains\Notifications\ValueObjects\NotificationSubjectType;
use App\Domains\Notifications\ValueObjects\NotificationType;
use App\Domains\Notifications\ValueObjects\NotifiedAppointment;
use App\Domains\Notifications\ValueObjects\NotifiedCustomer;
use App\Domains\Notifications\ValueObjects\NotifiedStaffMember;
use App\Domains\Notifications\ValueObjects\Payloads\AppointmentBookedPayload;
use App\Domains\Notifications\ValueObjects\Payloads\NotificationPayload;
use App\Domains\Notifications\ValueObjects\Payloads\StaffScheduleChangedPayload;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationEventModel>
 */
final class NotificationEventModelFactory extends Factory
{
    protected $model = NotificationEventModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventId = (string) Str::uuid7();

        return [
            'uuid' => $eventId,
            'business_id' => fn (): int => BusinessModel::factory()->create()->id,
            'type' => NotificationType::StaffScheduleChanged,
            'subject_type' => NotificationSubjectType::StaffMember,
            'subject_id' => fn (array $attributes): int => StaffMemberModel::factory()
                ->create(['business_id' => $attributes['business_id']])
                ->id,
            'payload' => fn (array $attributes): array => self::staffScheduleChangedPayloadOf(
                StaffMemberModel::query()->withTrashed()->findOrFail($attributes['subject_id']),
            )->toArray(),
            'idempotency_key' => $eventId,
            'occurred_at' => (new DateTimeImmutable)->format(DATE_ATOM),
        ];
    }

    public function aboutAppointment(AppointmentModel $appointment): self
    {
        return $this->state(fn (array $attributes): array => self::attributesFor(
            self::appointmentBookedPayloadOf($appointment),
            (int) $appointment->business_id,
            (int) $appointment->id,
            (string) $attributes['uuid'],
        ));
    }

    public function aboutStaffMember(StaffMemberModel $staffMember): self
    {
        return $this->state(fn (array $attributes): array => self::attributesFor(
            self::staffScheduleChangedPayloadOf($staffMember),
            (int) $staffMember->business_id,
            (int) $staffMember->id,
            (string) $attributes['uuid'],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private static function attributesFor(
        NotificationPayload $payload,
        int $businessKey,
        int $subjectKey,
        string $eventId,
    ): array {
        return [
            'business_id' => $businessKey,
            'type' => $payload->type(),
            'subject_type' => $payload->subject()->type,
            'subject_id' => $subjectKey,
            'payload' => $payload->toArray(),
            'idempotency_key' => $payload->idempotencyKey($eventId),
        ];
    }

    private static function appointmentBookedPayloadOf(AppointmentModel $appointment): AppointmentBookedPayload
    {
        $appointment->loadMissing(['service', 'customer']);

        /** @var DateTimeImmutable $startsAt */
        $startsAt = $appointment->starts_at;

        /** @var DateTimeImmutable $endsAt */
        $endsAt = $appointment->ends_at;

        return new AppointmentBookedPayload(
            appointment: new NotifiedAppointment(
                appointmentId: $appointment->uuid,
                startsAt: $startsAt,
                endsAt: $endsAt,
                serviceName: (string) $appointment->service->name,
                referenceCode: (string) $appointment->reference_code,
            ),
            customer: new NotifiedCustomer(
                customerId: $appointment->customer->uuid,
                name: (string) $appointment->customer->name,
            ),
        );
    }

    private static function staffScheduleChangedPayloadOf(StaffMemberModel $staffMember): StaffScheduleChangedPayload
    {
        return new StaffScheduleChangedPayload(new NotifiedStaffMember(
            staffMemberId: $staffMember->uuid,
            name: (string) $staffMember->account->name,
        ));
    }
}
