<?php

declare(strict_types=1);

namespace App\Domains\Appointments\ValueObjects;

use App\Domains\Appointments\Exceptions\AppointmentStaffNotPermitted;

final readonly class CalendarScope
{
    private function __construct(
        private ?string $ownerStaffMemberId,
    ) {}

    public static function everyone(): self
    {
        return new self(null);
    }

    public static function ownedBy(string $staffMemberId): self
    {
        return new self($staffMemberId);
    }

    public function permits(string $staffMemberId): bool
    {
        if ($this->ownerStaffMemberId === null) {
            return true;
        }

        return $this->ownerStaffMemberId === $staffMemberId;
    }

    /**
     * @throws AppointmentStaffNotPermitted
     */
    public function ensureMayAssign(string $staffMemberId): void
    {
        if (! $this->permits($staffMemberId)) {
            throw AppointmentStaffNotPermitted::withId($staffMemberId);
        }
    }

    public function restrictedStaffMemberId(): ?string
    {
        return $this->ownerStaffMemberId;
    }
}
