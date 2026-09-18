<?php

declare(strict_types=1);

namespace App\Domains\Appointments\ValueObjects;

use App\Domains\Appointments\Exceptions\InvalidAppointmentNotes;

final readonly class AppointmentNotes
{
    public const MAXIMUM_LENGTH = 2000;

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidAppointmentNotes
     */
    public static function fromString(string $value): self
    {
        $notes = trim($value);

        if (mb_strlen($notes) > self::MAXIMUM_LENGTH) {
            throw InvalidAppointmentNotes::tooLong(self::MAXIMUM_LENGTH);
        }

        return new self($notes);
    }

    /**
     * @throws InvalidAppointmentNotes
     */
    public static function fromNullable(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::fromString($value);
    }

    public static function restore(string $value): self
    {
        return new self($value);
    }
}
