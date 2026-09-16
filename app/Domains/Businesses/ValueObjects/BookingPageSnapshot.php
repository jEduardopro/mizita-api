<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

final readonly class BookingPageSnapshot
{
    /**
     * @param  list<BookingPageImageSnapshot>  $gallery
     */
    public function __construct(
        public string $accentColor,
        public string $buttonShape,
        public string $theme,
        public ?string $bannerUrl,
        public array $gallery,
    ) {}
}
