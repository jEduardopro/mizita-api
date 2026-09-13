<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\Entities\Business;
use DateTimeImmutable;

/**
 * Output boundary. Entities never leave the application layer, so use cases
 * return this instead.
 */
final readonly class BusinessData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public string $timezone,
        public string $industryId,
        public DateTimeImmutable $createdAt,
    ) {}

    public static function fromEntity(Business $business): self
    {
        return new self(
            id: $business->id,
            name: $business->name(),
            slug: $business->slug(),
            timezone: $business->timezone(),
            industryId: $business->industryId,
            createdAt: $business->createdAt,
        );
    }
}
