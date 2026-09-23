<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

enum ContactFieldPreference: string
{
    case Hidden = 'hidden';

    case Optional = 'optional';

    case Required = 'required';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $preference): string => $preference->value, self::cases());
    }
}
