<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

final readonly class BookingPageStyle
{
    public function __construct(
        public string $accentColor,
        public string $buttonShape,
        public string $theme,
    ) {}
}
