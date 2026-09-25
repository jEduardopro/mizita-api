<?php

declare(strict_types=1);

namespace App\Domains\Accounts\ValueObjects;

use DateTimeImmutable;

final readonly class ClosedBusinessSnapshot
{
    public function __construct(
        public string $id,
        public string $name,
        public DateTimeImmutable $closedAt,
        public DateTimeImmutable $purgeScheduledAt,
        public bool $purged,
    ) {}
}
