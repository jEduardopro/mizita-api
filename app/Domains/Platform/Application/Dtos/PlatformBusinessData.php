<?php

declare(strict_types=1);

namespace App\Domains\Platform\Application\Dtos;

use App\Domains\Platform\ValueObjects\Plan;
use App\Domains\Platform\ValueObjects\PlatformBusinessOwner;
use App\Domains\Platform\ValueObjects\PlatformBusinessRecord;
use DateTimeImmutable;

final readonly class PlatformBusinessData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public DateTimeImmutable $createdAt,
        public ?PlatformBusinessOwner $owner,
        public int $servicesCount,
        public int $customersCount,
        public Plan $plan,
    ) {}

    public static function fromRecord(PlatformBusinessRecord $business, Plan $plan): self
    {
        return new self(
            id: $business->id,
            name: $business->name,
            slug: $business->slug,
            createdAt: $business->createdAt,
            owner: $business->owner,
            servicesCount: $business->servicesCount,
            customersCount: $business->customersCount,
            plan: $plan,
        );
    }
}
