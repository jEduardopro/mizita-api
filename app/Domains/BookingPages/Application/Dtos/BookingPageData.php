<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Application\Dtos;

use App\Domains\BookingPages\ValueObjects\BookingPageImage;

final readonly class BookingPageData
{
    /**
     * @param  list<BookingPageImage>  $gallery
     */
    public function __construct(
        public string $id,
        public string $accentColor,
        public string $buttonShape,
        public string $theme,
        public ?string $bannerUrl,
        public array $gallery,
    ) {}
}
