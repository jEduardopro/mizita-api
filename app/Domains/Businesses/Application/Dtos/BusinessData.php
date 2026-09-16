<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\Entities\Business;
use DateTimeImmutable;

final readonly class BusinessData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public string $timezone,
        public string $industryId,
        public ?string $contactEmail,
        public ?string $about,
        public string $currency,
        public DateTimeImmutable $createdAt,
    ) {}

    public static function fromEntity(Business $business): self
    {
        return new self(
            id: $business->id,
            name: $business->name(),
            slug: $business->slug(),
            timezone: $business->timezone(),
            industryId: $business->industryId(),
            contactEmail: $business->contactEmail(),
            about: $business->about(),
            currency: $business->currency(),
            createdAt: $business->createdAt,
        );
    }
}
