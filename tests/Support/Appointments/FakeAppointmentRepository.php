<?php

declare(strict_types=1);

namespace Tests\Support\Appointments;

use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Domains\Appointments\Entities\Appointment;
use App\Domains\Appointments\Exceptions\AppointmentNotFound;
use App\Domains\Appointments\ValueObjects\CalendarRange;
use App\Domains\Appointments\ValueObjects\CalendarScope;
use App\Domains\Appointments\ValueObjects\CustomerAppointmentQuery;
use App\Shared\ValueObjects\Paginated;
use DateTimeImmutable;
use Throwable;

final class FakeAppointmentRepository implements AppointmentRepository
{
    /**
     * @var array<string, Appointment>
     */
    private array $appointments = [];

    private ?Throwable $saveFailure = null;

    /**
     * @var list<Appointment>
     */
    public array $saved = [];

    /**
     * @var list<array{businessId: string, id: string}>
     */
    public array $deleted = [];

    /**
     * @var list<string>
     */
    public array $businessIdsSeen = [];

    /**
     * @var list<array{businessId: string, from: string, to: string}>
     */
    public array $searches = [];

    /**
     * @var list<array{businessId: string, referenceCode: string}>
     */
    public array $referenceCodeLookups = [];

    /**
     * @var list<CustomerAppointmentQuery>
     */
    public array $customerQueries = [];

    /**
     * @var list<CalendarScope>
     */
    public array $scopesSeen = [];

    /**
     * @var list<array{businessId: string, staffMemberId: string, now: string}>
     */
    public array $upcomingChecks = [];

    /**
     * @var list<array{businessId: string, now: string}>
     */
    public array $upcomingCounts = [];

    public function __construct(
        public readonly AppointmentJournal $journal = new AppointmentJournal,
    ) {}

    public function store(Appointment ...$appointments): self
    {
        foreach ($appointments as $appointment) {
            $this->appointments[$this->keyFor($appointment->businessId, $appointment->id)] = $appointment;
        }

        return $this;
    }

    public function failingOnSave(Throwable $failure): self
    {
        $this->saveFailure = $failure;

        return $this;
    }

    /**
     * @return list<Appointment>
     */
    public function search(string $businessId, CalendarRange $range, CalendarScope $scope): array
    {
        $this->journal->record('appointments.search');
        $this->businessIdsSeen[] = $businessId;
        $this->scopesSeen[] = $scope;
        $this->searches[] = [
            'businessId' => $businessId,
            'from' => $range->from->format(DATE_ATOM),
            'to' => $range->to->format(DATE_ATOM),
        ];

        return array_values(array_filter(
            $this->appointments,
            static function (Appointment $appointment) use ($businessId, $range, $scope): bool {
                if ($appointment->businessId !== $businessId) {
                    return false;
                }

                if (! $scope->permits($appointment->staffMemberId())) {
                    return false;
                }

                $startsAt = $appointment->slot()->startsAt->getTimestamp();

                return $startsAt >= $range->from->getTimestamp() && $startsAt < $range->to->getTimestamp();
            },
        ));
    }

    /**
     * @return Paginated<Appointment>
     */
    public function bookedForCustomer(
        string $businessId,
        CustomerAppointmentQuery $query,
        CalendarScope $scope,
    ): Paginated {
        $this->journal->record('appointments.bookedForCustomer');
        $this->businessIdsSeen[] = $businessId;
        $this->customerQueries[] = $query;
        $this->scopesSeen[] = $scope;

        $booked = $this->bookedOf($businessId, $query->customerId, $scope);

        usort(
            $booked,
            static fn (Appointment $left, Appointment $right): int => $right->slot()->startsAt->getTimestamp()
                <=> $left->slot()->startsAt->getTimestamp(),
        );

        return Paginated::of(
            array_slice($booked, $query->pagination->offset(), $query->pagination->perPage),
            count($booked),
            $query->pagination,
        );
    }

    /**
     * @return list<Appointment>
     */
    private function bookedOf(string $businessId, string $customerId, CalendarScope $scope): array
    {
        return array_values(array_filter(
            $this->appointments,
            static fn (Appointment $appointment): bool => $appointment->businessId === $businessId
                && $appointment->customerId() === $customerId
                && $scope->permits($appointment->staffMemberId())
                && ! $appointment->isCancelled(),
        ));
    }

    public function findForBusiness(string $businessId, string $id): Appointment
    {
        $this->journal->record('appointments.find');
        $this->businessIdsSeen[] = $businessId;

        return $this->appointments[$this->keyFor($businessId, $id)]
            ?? throw AppointmentNotFound::withId($id);
    }

    public function findWithinScope(string $businessId, string $id, CalendarScope $scope): Appointment
    {
        $this->journal->record('appointments.findWithinScope');
        $this->businessIdsSeen[] = $businessId;
        $this->scopesSeen[] = $scope;

        $appointment = $this->appointments[$this->keyFor($businessId, $id)] ?? null;

        if ($appointment === null || ! $scope->permits($appointment->staffMemberId())) {
            throw AppointmentNotFound::withId($id);
        }

        return $appointment;
    }

    public function hasUpcomingForStaffMember(string $businessId, string $staffMemberId, DateTimeImmutable $now): bool
    {
        $this->journal->record('appointments.hasUpcomingForStaffMember');
        $this->businessIdsSeen[] = $businessId;
        $this->upcomingChecks[] = [
            'businessId' => $businessId,
            'staffMemberId' => $staffMemberId,
            'now' => $now->format(DATE_ATOM),
        ];

        foreach ($this->appointments as $appointment) {
            if ($appointment->businessId !== $businessId || $appointment->staffMemberId() !== $staffMemberId) {
                continue;
            }

            if (! $appointment->isCancelled() && $appointment->slot()->endsAt > $now) {
                return true;
            }
        }

        return false;
    }

    public function countUpcomingForBusiness(string $businessId, DateTimeImmutable $now): int
    {
        $this->journal->record('appointments.countUpcomingForBusiness');
        $this->businessIdsSeen[] = $businessId;
        $this->upcomingCounts[] = [
            'businessId' => $businessId,
            'now' => $now->format(DATE_ATOM),
        ];

        return count(array_filter(
            $this->appointments,
            static fn (Appointment $appointment): bool => $appointment->businessId === $businessId
                && ! $appointment->isCancelled()
                && $appointment->slot()->endsAt > $now,
        ));
    }

    public function findByReferenceCode(string $businessId, string $referenceCode): ?Appointment
    {
        $this->journal->record('appointments.findByReferenceCode');
        $this->businessIdsSeen[] = $businessId;
        $this->referenceCodeLookups[] = ['businessId' => $businessId, 'referenceCode' => $referenceCode];

        foreach ($this->appointments as $appointment) {
            if ($appointment->businessId !== $businessId) {
                continue;
            }

            if ($appointment->referenceCode()?->value === $referenceCode) {
                return $appointment;
            }
        }

        return null;
    }

    public function save(Appointment $appointment): void
    {
        $this->journal->record('appointments.save');

        if ($this->saveFailure !== null) {
            throw $this->saveFailure;
        }

        $this->appointments[$this->keyFor($appointment->businessId, $appointment->id)] = $appointment;
        $this->saved[] = $appointment;
    }

    public function delete(string $businessId, string $id): void
    {
        $this->journal->record('appointments.delete');
        $this->businessIdsSeen[] = $businessId;
        $key = $this->keyFor($businessId, $id);

        if (! isset($this->appointments[$key])) {
            throw AppointmentNotFound::withId($id);
        }

        unset($this->appointments[$key]);

        $this->deleted[] = ['businessId' => $businessId, 'id' => $id];
    }

    private function keyFor(string $businessId, string $id): string
    {
        return $businessId.'|'.$id;
    }
}
