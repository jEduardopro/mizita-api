<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\BookingPages\Contracts\BookingPageImages;
use App\Domains\BookingPages\Contracts\BookingPageRepository;
use App\Domains\PublicCatalog\Contracts\PublishedBanner;

final class BookingPagesPublishedBanner implements PublishedBanner
{
    public function __construct(
        private readonly BookingPageRepository $pages,
        private readonly BookingPageImages $images,
    ) {}

    public function originalUrlForBusiness(string $businessId): ?string
    {
        $page = $this->pages->findForBusiness($businessId);

        if ($page === null) {
            return null;
        }

        return $this->images->originalBannerUrlFor($businessId, $page->id);
    }
}
