<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

final readonly class CalendarScope
{
    private function __construct(
        private ?string $staffMemberId,
    ) {}

    public static function everyone(): self
    {
        return new self(null);
    }

    public static function ownedBy(string $staffMemberId): self
    {
        return new self($staffMemberId);
    }

    public function isRestricted(): bool
    {
        return $this->staffMemberId !== null;
    }

    public function covers(AppointmentSnapshot $appointment): bool
    {
        if ($this->staffMemberId === null) {
            return true;
        }

        return $this->staffMemberId === $appointment->staffMemberId;
    }
}
