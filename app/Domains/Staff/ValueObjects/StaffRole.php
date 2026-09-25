<?php

declare(strict_types=1);

namespace App\Domains\Staff\ValueObjects;

use App\Domains\Staff\Exceptions\InvalidTeamLevel;

enum StaffRole: string
{
    case Owner = 'owner';

    case Member = 'staff';

    case NoAccess = 'no_access';

    /**
     * @throws InvalidTeamLevel
     */
    public static function assignableFrom(string $level): self
    {
        $role = self::tryFrom($level);

        if ($role === null) {
            throw InvalidTeamLevel::unknown($level);
        }

        if (! $role->isAssignable()) {
            throw InvalidTeamLevel::ownerNotAssignable();
        }

        return $role;
    }

    /**
     * @return list<string>
     */
    public static function assignableValues(): array
    {
        return array_values(array_map(
            static fn (self $role): string => $role->value,
            array_filter(self::cases(), static fn (self $role): bool => $role->isAssignable()),
        ));
    }

    public function isAssignable(): bool
    {
        return $this !== self::Owner;
    }

    public function grantsAccess(): bool
    {
        return $this !== self::NoAccess;
    }
}
