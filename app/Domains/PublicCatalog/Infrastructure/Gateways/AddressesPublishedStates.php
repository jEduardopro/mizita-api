<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\Addresses\Application\Dtos\ListStatesInput;
use App\Domains\Addresses\Application\Dtos\StateData;
use App\Domains\Addresses\Application\UseCases\ListStates;
use App\Domains\PublicCatalog\Contracts\PublishedStates;
use App\Domains\PublicCatalog\ValueObjects\PublicState;
use App\Shared\ValueObjects\CountryCode;

final class AddressesPublishedStates implements PublishedStates
{
    public function __construct(
        private readonly ListStates $listStates,
    ) {}

    /**
     * @return list<PublicState>
     */
    public function activeFor(CountryCode $country): array
    {
        $response = $this->listStates->handle(new ListStatesInput($country->value));

        return array_map(
            static fn (StateData $state): PublicState => new PublicState(
                id: $state->id,
                code: $state->code,
                name: $state->name,
            ),
            $response->value(),
        );
    }
}
