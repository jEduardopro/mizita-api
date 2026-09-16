<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Application\UseCases;

use App\Domains\Addresses\Application\Dtos\ListStatesInput;
use App\Domains\Addresses\Application\Dtos\StateData;
use App\Domains\Addresses\Contracts\StateCatalog;
use App\Domains\Addresses\Entities\State;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class ListStates
{
    public function __construct(
        private readonly StateCatalog $states,
    ) {}

    /**
     * @return UseCaseResponse<list<StateData>>
     */
    public function handle(ListStatesInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            return UseCaseResponse::success(array_map(
                static fn (State $state): StateData => StateData::fromEntity($state),
                $this->states->allActiveFor($input->country()),
            ));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
