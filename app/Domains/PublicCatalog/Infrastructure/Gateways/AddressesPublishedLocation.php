<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\Addresses\Contracts\AddressRepository;
use App\Domains\Addresses\Infrastructure\Eloquent\Models\StateModel;
use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use App\Domains\PublicCatalog\Contracts\PublishedLocation;
use App\Domains\PublicCatalog\ValueObjects\PublicLocation;

final class AddressesPublishedLocation implements PublishedLocation
{
    private const COORDINATE_SCALE = 7;

    private const DECIMAL_SEPARATOR = '.';

    public function __construct(
        private readonly AddressRepository $addresses,
    ) {}

    public function forBusiness(string $businessId): ?PublicLocation
    {
        $address = $this->addresses->findForOwner(AddressOwnerType::Business, $businessId);

        if ($address === null) {
            return null;
        }

        $coordinates = $address->coordinates();

        return new PublicLocation(
            street: $address->street(),
            city: $address->city(),
            state: self::stateNameFor($address->stateId()),
            postalCode: $address->postalCode()->value,
            countryCode: $address->country()->value,
            latitude: self::decimal($coordinates?->latitude),
            longitude: self::decimal($coordinates?->longitude),
        );
    }

    private static function stateNameFor(?string $stateId): ?string
    {
        if ($stateId === null) {
            return null;
        }

        $name = StateModel::query()->where('uuid', $stateId)->value('name');

        return $name === null ? null : (string) $name;
    }

    private static function decimal(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return number_format($value, self::COORDINATE_SCALE, self::DECIMAL_SEPARATOR, '');
    }
}
