<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Application\Dtos;

use App\Domains\Notifications\Exceptions\NotifiedStaffMemberNotFound;
use App\Domains\Notifications\ValueObjects\Identifier;

final readonly class NotifyStaffScheduleChangedInput
{
    public function __construct(
        public string $businessId,
        public string $staffMemberId,
    ) {}

    /**
     * @throws NotifiedStaffMemberNotFound
     */
    public function validate(): void
    {
        $this->validateIdentifiers();
    }

    private function validateIdentifiers(): void
    {
        if (Identifier::isWellFormed($this->businessId) && Identifier::isWellFormed($this->staffMemberId)) {
            return;
        }

        throw NotifiedStaffMemberNotFound::inBusiness($this->businessId, $this->staffMemberId);
    }
}
