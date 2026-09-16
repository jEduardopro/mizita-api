<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\ValueObjects;

final readonly class BookingPageImage
{
    public function __construct(
        public string $id,
        public string $url,
        public int $position,
    ) {}
}
