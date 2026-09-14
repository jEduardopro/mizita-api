<?php

declare(strict_types=1);

namespace App\Domains\Phones\Entities;

use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\ValueObjects\PhoneNumber;
use DateTimeImmutable;

final class Phone
{
    private function __construct(
        public readonly string $id,
        public readonly PhoneOwnerType $ownerType,
        public readonly string $ownerId,
        private PhoneNumber $number,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        string $id,
        PhoneOwnerType $ownerType,
        string $ownerId,
        PhoneNumber $number,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            ownerType: $ownerType,
            ownerId: $ownerId,
            number: $number,
            createdAt: $now,
        );
    }

    public static function restore(
        string $id,
        PhoneOwnerType $ownerType,
        string $ownerId,
        PhoneNumber $number,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            ownerType: $ownerType,
            ownerId: $ownerId,
            number: $number,
            createdAt: $createdAt,
        );
    }

    public function changeNumber(PhoneNumber $number): void
    {
        $this->number = $number;
    }

    public function number(): PhoneNumber
    {
        return $this->number;
    }
}
