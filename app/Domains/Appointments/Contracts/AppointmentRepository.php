<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Contracts;

use App\Domains\Appointments\Entities\Appointment;
use App\Domains\Appointments\Exceptions\AppointmentNotFound;
use App\Domains\Appointments\Exceptions\AppointmentOverlaps;
use App\Domains\Appointments\ValueObjects\CalendarRange;
use App\Domains\Appointments\ValueObjects\CalendarScope;
use App\Domains\Appointments\ValueObjects\CustomerAppointmentQuery;
use App\Shared\ValueObjects\Paginated;
use DateTimeImmutable;

interface AppointmentRepository
{
    /**
     * @return list<Appointment>
     */
    public function search(string $businessId, CalendarRange $range, CalendarScope $scope): array;

    /**
     * @return Paginated<Appointment>
     */
    public function bookedForCustomer(
        string $businessId,
        CustomerAppointmentQuery $query,
        CalendarScope $scope,
    ): Paginated;

    /**
     * @throws AppointmentNotFound
     */
    public function findForBusiness(string $businessId, string $id): Appointment;

    /**
     * @throws AppointmentNotFound
     */
    public function findWithinScope(string $businessId, string $id, CalendarScope $scope): Appointment;

    public function hasUpcomingForStaffMember(string $businessId, string $staffMemberId, DateTimeImmutable $now): bool;

    public function findByReferenceCode(string $businessId, string $referenceCode): ?Appointment;

    /**
     * @throws AppointmentOverlaps
     */
    public function save(Appointment $appointment): void;

    /**
     * @throws AppointmentNotFound
     */
    public function delete(string $businessId, string $id): void;
}
