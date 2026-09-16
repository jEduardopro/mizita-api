<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Application\UseCases;

use App\Domains\Addresses\Application\Dtos\AddressData;
use App\Domains\Addresses\Application\Dtos\ReplaceAddressInput;
use App\Domains\Addresses\Contracts\AddressRepository;
use App\Domains\Addresses\Entities\Address;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;

final class ReplaceAddress
{
    public function __construct(
        private readonly AddressRepository $addresses,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<AddressData>
     */
    public function handle(ReplaceAddressInput $input): UseCaseResponse
    {
        try {
            $address = $this->addressFor($input);

            $this->addresses->save($address);

            return UseCaseResponse::success(AddressData::fromEntity($address));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    private function addressFor(ReplaceAddressInput $input): Address
    {
        $address = $this->addresses->findForOwner($input->ownerType, $input->ownerId);

        if ($address === null) {
            return Address::create(
                id: $this->ids->next(),
                ownerType: $input->ownerType,
                ownerId: $input->ownerId,
                street: $input->street,
                city: $input->city,
                stateId: $input->stateId,
                postalCode: $input->postalCode,
                country: $input->country,
                coordinates: $input->coordinates,
                now: $this->clock->now(),
            );
        }

        $address->relocateTo(
            street: $input->street,
            city: $input->city,
            stateId: $input->stateId,
            postalCode: $input->postalCode,
            country: $input->country,
        );

        $this->pin($address, $input);

        return $address;
    }

    private function pin(Address $address, ReplaceAddressInput $input): void
    {
        if ($input->coordinates === null) {
            $address->unpin();

            return;
        }

        $address->pinAt($input->coordinates);
    }
}
