<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\UseCases;

use App\Domains\PublicCatalog\Application\Dtos\BusinessPageSharePreview;
use App\Domains\PublicCatalog\Application\Dtos\DescribeBusinessPageSharePreviewInput;
use App\Domains\PublicCatalog\Contracts\PublishedBanner;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Contracts\PublishedCity;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class DescribeBusinessPageSharePreview
{
    public function __construct(
        private readonly PublishedBusinesses $businesses,
        private readonly PublishedBanner $banner,
        private readonly PublishedCity $city,
    ) {}

    /**
     * @return UseCaseResponse<BusinessPageSharePreview>
     */
    public function handle(DescribeBusinessPageSharePreviewInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            return UseCaseResponse::success($this->previewOf($input->slug));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws BusinessPageNotFound
     */
    private function previewOf(string $slug): BusinessPageSharePreview
    {
        $profile = $this->businesses->findBySlug($slug);

        return new BusinessPageSharePreview(
            name: $profile->name,
            city: $this->city->forBusiness($profile->id),
            about: $profile->about,
            imageUrl: $this->imageOf($profile->id),
        );
    }

    private function imageOf(string $businessId): ?string
    {
        return $this->banner->originalUrlForBusiness($businessId)
            ?? $this->businesses->originalLogoUrlFor($businessId);
    }
}
