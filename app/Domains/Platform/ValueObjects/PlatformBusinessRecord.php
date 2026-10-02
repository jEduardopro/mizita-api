<?php

declare(strict_types=1);

namespace App\Domains\Platform\ValueObjects;

use DateTimeImmutable;

final readonly class PlatformBusinessRecord
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public DateTimeImmutable $createdAt,
        public ?PlatformBusinessOwner $owner,
        public int $servicesCount,
        public int $customersCount,
    ) {}
}
