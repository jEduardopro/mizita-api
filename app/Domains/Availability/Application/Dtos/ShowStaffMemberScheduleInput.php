<?php

declare(strict_types=1);

namespace App\Domains\Availability\Application\Dtos;

use App\Domains\Availability\Exceptions\StaffMemberNotFound;
use App\Domains\Availability\ValueObjects\Identifier;

final readonly class ShowStaffMemberScheduleInput
{
    public function __construct(
        public string $staffMemberId,
    ) {}

    /**
     * @throws StaffMemberNotFound
     */
    public function validate(): void
    {
        $this->validateStaffMemberId();
    }

    private function validateStaffMemberId(): void
    {
        if (! Identifier::isWellFormed($this->staffMemberId)) {
            throw StaffMemberNotFound::withId($this->staffMemberId);
        }
    }
}
