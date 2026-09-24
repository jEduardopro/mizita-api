<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\Dtos;

use App\Domains\Services\Exceptions\UnknownStaffMember;

final readonly class ListServicesForStaffInput
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    public function __construct(
        public string $staffMemberId,
    ) {}

    /**
     * @throws UnknownStaffMember
     */
    public function validate(): void
    {
        $this->validateStaffMemberId();
    }

    private function validateStaffMemberId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->staffMemberId) !== 1) {
            throw UnknownStaffMember::amongSelected();
        }
    }
}
