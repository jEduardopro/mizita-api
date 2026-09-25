<?php

declare(strict_types=1);

namespace App\Domains\Staff\ValueObjects;

use App\Domains\Staff\Exceptions\StaffMemberNotFound;

final readonly class StaffMemberId
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws StaffMemberNotFound
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::UUID_PATTERN, $value) !== 1) {
            throw StaffMemberNotFound::withId($value);
        }

        return new self($value);
    }
}
