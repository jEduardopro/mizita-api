<?php

declare(strict_types=1);

namespace App\Domains\Customers\Entities;

use App\Domains\Customers\Exceptions\InvalidCustomerBirthDate;
use App\Domains\Customers\Exceptions\InvalidCustomerName;
use App\Domains\Customers\Exceptions\InvalidCustomerNotes;
use App\Domains\Customers\ValueObjects\CustomerEmail;
use DateTimeImmutable;

final class Customer
{
    public const MAXIMUM_NAME_LENGTH = 120;

    public const MAXIMUM_NOTES_LENGTH = 2000;

    public const EARLIEST_BIRTH_YEAR = 1900;

    private function __construct(
        public readonly string $id,
        public readonly string $businessId,
        private string $name,
        private ?CustomerEmail $email,
        private ?DateTimeImmutable $birthDate,
        private ?string $notes,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    /**
     * @throws InvalidCustomerName
     * @throws InvalidCustomerBirthDate
     * @throws InvalidCustomerNotes
     */
    public static function create(
        string $id,
        string $businessId,
        string $name,
        ?CustomerEmail $email,
        ?DateTimeImmutable $birthDate,
        ?string $notes,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            name: self::acceptableName($name),
            email: $email,
            birthDate: self::acceptableBirthDate($birthDate, $now),
            notes: self::acceptableNotes($notes),
            createdAt: $now,
        );
    }

    public static function restore(
        string $id,
        string $businessId,
        string $name,
        ?CustomerEmail $email,
        ?DateTimeImmutable $birthDate,
        ?string $notes,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            name: $name,
            email: $email,
            birthDate: $birthDate,
            notes: $notes,
            createdAt: $createdAt,
        );
    }

    /**
     * @throws InvalidCustomerName
     */
    public function rename(string $name): void
    {
        $this->name = self::acceptableName($name);
    }

    public function changeEmail(?CustomerEmail $email): void
    {
        $this->email = $email;
    }

    /**
     * @throws InvalidCustomerBirthDate
     */
    public function changeBirthDate(?DateTimeImmutable $birthDate, DateTimeImmutable $now): void
    {
        $this->birthDate = self::acceptableBirthDate($birthDate, $now);
    }

    /**
     * @throws InvalidCustomerNotes
     */
    public function changeNotes(?string $notes): void
    {
        $this->notes = self::acceptableNotes($notes);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): ?CustomerEmail
    {
        return $this->email;
    }

    public function birthDate(): ?DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    /**
     * @throws InvalidCustomerName
     */
    private static function acceptableName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw InvalidCustomerName::empty();
        }

        if (mb_strlen($name) > self::MAXIMUM_NAME_LENGTH) {
            throw InvalidCustomerName::tooLong(self::MAXIMUM_NAME_LENGTH);
        }

        return $name;
    }

    /**
     * @throws InvalidCustomerBirthDate
     */
    private static function acceptableBirthDate(?DateTimeImmutable $birthDate, DateTimeImmutable $now): ?DateTimeImmutable
    {
        if ($birthDate === null) {
            return null;
        }

        if ($birthDate > $now) {
            throw InvalidCustomerBirthDate::inTheFuture();
        }

        if ((int) $birthDate->format('Y') < self::EARLIEST_BIRTH_YEAR) {
            throw InvalidCustomerBirthDate::tooEarly(self::EARLIEST_BIRTH_YEAR);
        }

        return $birthDate;
    }

    /**
     * @throws InvalidCustomerNotes
     */
    private static function acceptableNotes(?string $notes): ?string
    {
        if ($notes === null) {
            return null;
        }

        $notes = trim($notes);

        if ($notes === '') {
            return null;
        }

        if (mb_strlen($notes) > self::MAXIMUM_NOTES_LENGTH) {
            throw InvalidCustomerNotes::tooLong(self::MAXIMUM_NOTES_LENGTH);
        }

        return $notes;
    }
}
