<?php

declare(strict_types=1);

namespace App\Domains\Platform\Entities;

use App\Domains\Platform\Exceptions\InvalidPlatformAdminName;
use App\Domains\Platform\ValueObjects\PlatformAdminEmail;
use DateTimeImmutable;

final class PlatformAdmin
{
    public const MAXIMUM_NAME_LENGTH = 255;

    private function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly PlatformAdminEmail $email,
        public readonly string $passwordHash,
        public readonly string $twoFactorSecret,
        public readonly DateTimeImmutable $twoFactorConfirmedAt,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    /**
     * @throws InvalidPlatformAdminName
     */
    public static function registerWithConfirmedAuthenticator(
        string $id,
        string $name,
        PlatformAdminEmail $email,
        string $passwordHash,
        string $twoFactorSecret,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            name: self::normalizeName($name),
            email: $email,
            passwordHash: $passwordHash,
            twoFactorSecret: $twoFactorSecret,
            twoFactorConfirmedAt: $now,
            createdAt: $now,
        );
    }

    /**
     * @throws InvalidPlatformAdminName
     */
    private static function normalizeName(string $name): string
    {
        $normalized = trim($name);

        if ($normalized === '') {
            throw InvalidPlatformAdminName::empty();
        }

        if (mb_strlen($normalized) > self::MAXIMUM_NAME_LENGTH) {
            throw InvalidPlatformAdminName::tooLong(self::MAXIMUM_NAME_LENGTH);
        }

        return $normalized;
    }
}
