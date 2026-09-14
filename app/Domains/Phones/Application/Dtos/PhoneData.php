<?php

declare(strict_types=1);

namespace App\Domains\Phones\Application\Dtos;

use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\ValueObjects\PhoneNumber;
use DateTimeImmutable;

final readonly class PhoneData
{
    public function __construct(
        public string $id,
        public PhoneOwnerType $ownerType,
        public string $ownerId,
        public PhoneNumber $number,
        public DateTimeImmutable $createdAt,
    ) {}

    public static function fromEntity(Phone $phone): self
    {
        return new self(
            id: $phone->id,
            ownerType: $phone->ownerType,
            ownerId: $phone->ownerId,
            number: $phone->number(),
            createdAt: $phone->createdAt,
        );
    }
}
