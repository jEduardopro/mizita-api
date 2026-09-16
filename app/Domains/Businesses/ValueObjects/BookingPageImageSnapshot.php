<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

final readonly class BookingPageImageSnapshot
{
    public function __construct(
        public string $id,
        public string $url,
    ) {}
}
