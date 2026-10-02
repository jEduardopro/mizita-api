<?php

declare(strict_types=1);

namespace App\Domains\Platform\ValueObjects;

use App\Domains\Platform\Exceptions\InvalidPlatformAdminEmail;

final readonly class PlatformAdminEmail
{
    public const MAXIMUM_LENGTH = 255;

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidPlatformAdminEmail
     */
    public static function fromString(string $email): self
    {
        $normalized = mb_strtolower(trim($email));

        if ($normalized === '') {
            throw InvalidPlatformAdminEmail::empty();
        }

        if (mb_strlen($normalized) > self::MAXIMUM_LENGTH) {
            throw InvalidPlatformAdminEmail::tooLong(self::MAXIMUM_LENGTH);
        }

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidPlatformAdminEmail::malformed($normalized);
        }

        return new self($normalized);
    }
}
