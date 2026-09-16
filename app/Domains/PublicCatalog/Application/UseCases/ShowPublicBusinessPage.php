<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\UseCases;

use App\Domains\PublicCatalog\Application\Dtos\PublicBusinessPageData;
use App\Domains\PublicCatalog\Application\Dtos\ShowPublicBusinessPageInput;
use App\Domains\PublicCatalog\Application\Presenters\PublicBusinessPagePresenter;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class ShowPublicBusinessPage
{
    public function __construct(
        private readonly PublicBusinessPagePresenter $presenter,
    ) {}

    /**
     * @return UseCaseResponse<PublicBusinessPageData>
     */
    public function handle(ShowPublicBusinessPageInput $input): UseCaseResponse
    {
        try {
            return UseCaseResponse::success($this->presenter->describe($input->slug));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
