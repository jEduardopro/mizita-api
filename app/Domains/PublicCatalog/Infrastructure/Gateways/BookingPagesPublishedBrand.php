<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\BookingPages\Application\Presenters\BookingPagePresenter;
use App\Domains\BookingPages\Contracts\BookingPageRepository;
use App\Domains\BookingPages\Entities\BookingPage;
use App\Domains\BookingPages\ValueObjects\BookingPageImage;
use App\Domains\PublicCatalog\Contracts\PublishedBrand;
use App\Domains\PublicCatalog\ValueObjects\PublicBrand;
use App\Domains\PublicCatalog\ValueObjects\PublicGalleryImage;

final class BookingPagesPublishedBrand implements PublishedBrand
{
    public function __construct(
        private readonly BookingPageRepository $pages,
        private readonly BookingPagePresenter $presenter,
    ) {}

    public function forBusiness(string $businessId): PublicBrand
    {
        $page = $this->pages->findForBusiness($businessId);

        if ($page === null) {
            return self::unstyled();
        }

        $described = $this->presenter->describe($page);

        return new PublicBrand(
            accentColor: $described->accentColor,
            buttonShape: $described->buttonShape,
            theme: $described->theme,
            bannerUrl: $described->bannerUrl,
            gallery: array_map(
                static fn (BookingPageImage $image): PublicGalleryImage => new PublicGalleryImage(
                    id: $image->id,
                    url: $image->url,
                ),
                $described->gallery,
            ),
        );
    }

    private static function unstyled(): PublicBrand
    {
        return new PublicBrand(
            accentColor: BookingPage::DEFAULT_ACCENT_COLOR->value,
            buttonShape: BookingPage::DEFAULT_BUTTON_SHAPE->value,
            theme: BookingPage::DEFAULT_THEME->value,
            bannerUrl: null,
            gallery: [],
        );
    }
}
