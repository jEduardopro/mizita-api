<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Application\UseCases;

use App\Domains\Addresses\Application\Dtos\AddressData;
use App\Domains\Addresses\Application\Dtos\ReplaceAddressInput;
use App\Domains\Addresses\Contracts\AddressRepository;
use App\Domains\Addresses\Contracts\StateCatalog;
use App\Domains\Addresses\Entities\Address;
use App\Domains\Addresses\ValueObjects\AddressRegion;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;

final class ReplaceAddress
{
    public function __construct(
        private readonly AddressRepository $addresses,
        private readonly StateCatalog $states,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<?AddressData>
     */
    public function handle(ReplaceAddressInput $input): UseCaseResponse
    {
        try {
            $current = $this->addresses->findForOwner($input->ownerType, $input->ownerId);

            if ($current === null && self::carriesNoStreet($input)) {
                return UseCaseResponse::success(null);
            }

            $address = $current === null
                ? $this->registered($input)
                : $this->relocated($current, $input);

            $this->addresses->save($address);

            return UseCaseResponse::success(AddressData::fromEntity($address));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    private function registered(ReplaceAddressInput $input): Address
    {
        $region = $this->regionFor($input);

        return Address::create(
            id: $this->ids->next(),
            ownerType: $input->ownerType,
            ownerId: $input->ownerId,
            street: $input->street,
            city: $input->city,
            stateId: $region->stateId,
            postalCode: $input->postalCode,
            country: $region->country,
            coordinates: $input->coordinates,
            now: $this->clock->now(),
            stateName: $region->stateName,
        );
    }

    private function relocated(Address $address, ReplaceAddressInput $input): Address
    {
        $region = $this->regionFor($input);

        $address->relocateTo(
            street: $input->street,
            city: $input->city,
            stateId: $region->stateId,
            postalCode: $input->postalCode,
            country: $region->country,
            stateName: $region->stateName,
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

    private function regionFor(ReplaceAddressInput $input): AddressRegion
    {
        if ($input->stateId !== null) {
            return AddressRegion::chosen($input->stateId, $input->country);
        }

        if ($input->stateName === null) {
            return AddressRegion::typed(null, $input->country);
        }

        $cataloguedState = $this->states->findActiveByNameOrCodePreferring($input->country, $input->stateName);

        if ($cataloguedState === null) {
            return AddressRegion::typed($input->stateName, $input->country);
        }

        return AddressRegion::catalogued($cataloguedState);
    }

    private static function carriesNoStreet(ReplaceAddressInput $input): bool
    {
        return trim($input->street) === '';
    }
}
