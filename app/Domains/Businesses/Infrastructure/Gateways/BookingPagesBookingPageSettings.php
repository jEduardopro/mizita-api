<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Gateways;

use App\Domains\BookingPages\Application\Presenters\BookingPagePresenter;
use App\Domains\BookingPages\Contracts\BookingPageRepository;
use App\Domains\BookingPages\Contracts\CurrentBookingPage;
use App\Domains\BookingPages\ValueObjects\BookingPageImage;
use App\Domains\BookingPages\ValueObjects\BrandColor;
use App\Domains\BookingPages\ValueObjects\ButtonShape;
use App\Domains\BookingPages\ValueObjects\PageTheme;
use App\Domains\Businesses\Contracts\BookingPageSettings;
use App\Domains\Businesses\ValueObjects\BookingPageImageSnapshot;
use App\Domains\Businesses\ValueObjects\BookingPageSnapshot;
use App\Domains\Businesses\ValueObjects\BookingPageStyle;

final class BookingPagesBookingPageSettings implements BookingPageSettings
{
    public function __construct(
        private readonly CurrentBookingPage $pages,
        private readonly BookingPagePresenter $presenter,
        private readonly BookingPageRepository $repository,
    ) {}

    public function forBusiness(string $businessId): BookingPageSnapshot
    {
        $page = $this->presenter->describe($this->pages->forBusiness($businessId));

        return new BookingPageSnapshot(
            accentColor: $page->accentColor,
            buttonShape: $page->buttonShape,
            theme: $page->theme,
            bannerUrl: $page->bannerUrl,
            gallery: array_map(
                static fn (BookingPageImage $image): BookingPageImageSnapshot => new BookingPageImageSnapshot(
                    id: $image->id,
                    url: $image->url,
                ),
                $page->gallery,
            ),
        );
    }

    public function applyTo(string $businessId, BookingPageStyle $style): void
    {
        $page = $this->pages->forBusiness($businessId);

        $page->restyle(
            accentColor: BrandColor::fromValue($style->accentColor),
            buttonShape: ButtonShape::fromValue($style->buttonShape),
            theme: PageTheme::fromValue($style->theme),
        );

        $this->repository->save($page);
    }
}
