<?php

declare(strict_types=1);

namespace App\Domains\Accounts\ValueObjects;

use App\Domains\Accounts\Exceptions\TemporaryPasswordTooShort;

final readonly class TemporaryPassword
{
    public const MINIMUM_LENGTH = 16;

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws TemporaryPasswordTooShort
     */
    public static function fromString(string $value): self
    {
        if (mb_strlen($value) < self::MINIMUM_LENGTH) {
            throw TemporaryPasswordTooShort::belowMinimum(self::MINIMUM_LENGTH);
        }

        return new self($value);
    }
}
