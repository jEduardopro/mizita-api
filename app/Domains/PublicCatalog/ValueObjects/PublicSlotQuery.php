<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

final readonly class PublicSlotQuery
{
    public function __construct(
        public string $serviceId,
        public string $staffId,
        public string $from,
        public string $to,
    ) {}
}
