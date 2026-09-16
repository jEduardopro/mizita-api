<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

final readonly class BusinessLinkSnapshot
{
    public function __construct(
        public string $platform,
        public string $url,
        public int $position,
    ) {}
}
