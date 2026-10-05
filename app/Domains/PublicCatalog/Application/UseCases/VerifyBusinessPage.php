<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\UseCases;

use App\Domains\PublicCatalog\Application\Dtos\VerifyBusinessPageInput;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class VerifyBusinessPage
{
    public function __construct(
        private readonly PublishedBusinesses $businesses,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(VerifyBusinessPageInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $this->ensurePublished($input->slug);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success();
    }

    /**
     * @throws BusinessPageNotFound
     */
    private function ensurePublished(string $slug): void
    {
        if (! $this->businesses->existsBySlug($slug)) {
            throw BusinessPageNotFound::withSlug($slug);
        }
    }
}
