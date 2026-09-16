<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

final readonly class PublicScheduleEntry
{
    public function __construct(
        public int $weekday,
        public string $startsAt,
        public string $endsAt,
    ) {}
}
