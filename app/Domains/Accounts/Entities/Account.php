<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Entities;

use App\Domains\Accounts\Exceptions\InvalidAccountEmail;
use App\Domains\Accounts\Exceptions\InvalidAccountName;
use DateTimeImmutable;

final class Account
{
    public const MAXIMUM_NAME_LENGTH = 255;

    private function __construct(
        public readonly string $id,
        private string $name,
        private string $email,
        private ?DateTimeImmutable $emailVerifiedAt,
        public readonly DateTimeImmutable $createdAt,
    ) {}

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

    public function verifyEmail(DateTimeImmutable $now): void
    {
        if ($this->emailVerifiedAt !== null) {
            return;
        }

        $this->emailVerifiedAt = $now;
    }

    public function rename(string $name): void
    {
        $this->name = self::normalizeName($name);
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

        if (mb_strlen($name) > self::MAXIMUM_NAME_LENGTH) {
            throw InvalidAccountName::tooLong(self::MAXIMUM_NAME_LENGTH);
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
