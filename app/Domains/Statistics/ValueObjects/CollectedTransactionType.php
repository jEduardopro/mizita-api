<?php

declare(strict_types=1);

namespace App\Domains\Statistics\ValueObjects;

enum CollectedTransactionType: string
{
    case Approved = 'approved';
    case Void = 'void';
    case Refund = 'refund';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }

    public function sign(): int
    {
        return match ($this) {
            self::Approved => 1,
            self::Void, self::Refund => -1,
        };
    }
}
