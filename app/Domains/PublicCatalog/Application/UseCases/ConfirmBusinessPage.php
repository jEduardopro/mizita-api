<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\UseCases;

use App\Domains\PublicCatalog\Application\Dtos\BusinessPageIdentity;
use App\Domains\PublicCatalog\Application\Dtos\ConfirmBusinessPageInput;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class ConfirmBusinessPage
{
    public function __construct(
        private readonly PublishedBusinesses $businesses,
    ) {}

    /**
     * @return UseCaseResponse<BusinessPageIdentity>
     */
    public function handle(ConfirmBusinessPageInput $input): UseCaseResponse
    {
        try {
            return UseCaseResponse::success($this->identify($input->slug));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws BusinessPageNotFound
     */
    private function identify(string $slug): BusinessPageIdentity
    {
        if (! $this->businesses->existsBySlug($slug)) {
            throw BusinessPageNotFound::withSlug($slug);
        }

        return new BusinessPageIdentity($slug);
    }
}
