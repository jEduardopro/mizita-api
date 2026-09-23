<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\UseCases;

use App\Domains\PublicCatalog\Application\Dtos\ListPublicStatesInput;
use App\Domains\PublicCatalog\Contracts\PublishedStates;
use App\Domains\PublicCatalog\ValueObjects\PublicState;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class ListPublicStates
{
    public function __construct(
        private readonly PublishedStates $states,
    ) {}

    /**
     * @return UseCaseResponse<list<PublicState>>
     */
    public function handle(ListPublicStatesInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            return UseCaseResponse::success($this->states->activeFor($input->country()));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
