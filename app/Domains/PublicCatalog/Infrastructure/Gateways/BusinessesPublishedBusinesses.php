<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\BusinessLogo;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\ValueObjects\PublicBusinessProfile;

final class BusinessesPublishedBusinesses implements PublishedBusinesses
{
    public function __construct(
        private readonly BusinessRepository $businesses,
        private readonly BusinessLogo $logo,
    ) {}

    public function findBySlug(string $slug): PublicBusinessProfile
    {
        try {
            $business = $this->businesses->findBySlug($slug);
        } catch (BusinessNotFound $absent) {
            throw BusinessPageNotFound::withSlug($slug, $absent);
        }

        return new PublicBusinessProfile(
            id: $business->id,
            name: $business->name(),
            slug: $business->slug(),
            about: $business->about(),
            timezone: $business->timezone(),
            currencyCode: $business->currency(),
            logoUrl: $this->logo->urlFor($business->id),
        );
    }

    public function existsBySlug(string $slug): bool
    {
        return $this->businesses->existsBySlug($slug);
    }
}
