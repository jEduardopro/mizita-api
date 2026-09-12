<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Entities;

use App\Domains\Accounts\Exceptions\InvalidAccountEmail;
use App\Domains\Accounts\Exceptions\InvalidAccountName;
use DateTimeImmutable;

/**
 * Domain entity: plain PHP, no framework. An Account is who a person is on the
 * platform, independent of any business - it is a root domain concept.
 *
 * It deliberately does not carry the password hash. Credentials are an
 * authentication concern handled at the infrastructure edge, and keeping the
 * hash out of the entity means no write path through this domain can overwrite
 * one by accident.
 */
final class Account
{
    private function __construct(
        public readonly string $id,
        private string $name,
        private string $email,
        private ?DateTimeImmutable $emailVerifiedAt,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    /**
     * Registers a person whose email an identity provider has already proven
     * they control, so the account starts verified.
     */
    public static function registerWithVerifiedEmail(
        string $id,
        string $name,
        string $email,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            name: self::normalizeName($name),
            email: self::normalizeEmail($email),
            emailVerifiedAt: $now,
            createdAt: $now,
        );
    }

    /**
     * Rehydrates an account from storage. Skips creation-time rules by design:
     * the data was already valid when it was written.
     */
    public static function restore(
        string $id,
        string $name,
        string $email,
        ?DateTimeImmutable $emailVerifiedAt,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            email: $email,
            emailVerifiedAt: $emailVerifiedAt,
            createdAt: $createdAt,
        );
    }

    /**
     * Records that someone has proven control of this address. Verifying twice
     * keeps the first timestamp: when it happened is a fact, not a flag.
     */
    public function verifyEmail(DateTimeImmutable $now): void
    {
        if ($this->emailVerifiedAt !== null) {
            return;
        }

        $this->emailVerifiedAt = $now;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function emailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw InvalidAccountName::empty();
        }

        return $name;
    }

    private static function normalizeEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));

        if ($email === '') {
            throw InvalidAccountEmail::empty();
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidAccountEmail::malformed($email);
        }

        return $email;
    }
}
