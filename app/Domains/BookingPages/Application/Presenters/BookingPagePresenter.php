<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Application\Presenters;

use App\Domains\BookingPages\Application\Dtos\BookingPageData;
use App\Domains\BookingPages\Contracts\BookingPageImages;
use App\Domains\BookingPages\Entities\BookingPage;

final class BookingPagePresenter
{
    public function __construct(
        private readonly BookingPageImages $images,
    ) {}

    public function describe(BookingPage $page): BookingPageData
    {
        return new BookingPageData(
            id: $page->id,
            accentColor: $page->accentColor()->value,
            buttonShape: $page->buttonShape()->value,
            theme: $page->theme()->value,
            bannerUrl: $this->images->bannerUrlFor($page->id),
            gallery: $this->images->galleryFor($page->id),
        );
    }
}
