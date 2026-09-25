<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\StaffMemberId;

final readonly class ShowTeamMemberInput
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
        StaffMemberId::fromString($this->staffMemberId);
    }
}
