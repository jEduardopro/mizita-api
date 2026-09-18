<?php

declare(strict_types=1);

namespace App\Domains\Appointments\ValueObjects;

final readonly class Identifier
{
    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    private function __construct() {}

    public static function isWellFormed(string $value): bool
    {
        return preg_match(self::PATTERN, $value) === 1;
    }
}
