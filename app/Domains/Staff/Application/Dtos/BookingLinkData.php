<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

final readonly class BookingLinkData
{
    public function __construct(
        public string $slug,
        public string $url,
    ) {}
}
