<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\Dtos;

use App\Domains\Services\Exceptions\ServiceNotFound;
use App\Domains\Services\Exceptions\UnknownStaffMember;

final readonly class UnassignStaffFromServiceInput
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    public function __construct(
        public string $serviceId,
        public string $staffMemberId,
    ) {}

    /**
     * @throws ServiceNotFound
     * @throws UnknownStaffMember
     */
    public function validate(): void
    {
        $this->validateServiceId();
        $this->validateStaffMemberId();
    }

    private function validateServiceId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->serviceId) !== 1) {
            throw ServiceNotFound::withId($this->serviceId);
        }
    }

    private function validateStaffMemberId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->staffMemberId) !== 1) {
            throw UnknownStaffMember::amongSelected();
        }
    }
}
