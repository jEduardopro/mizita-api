<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Infrastructure\Eloquent\Mappers;

use App\Domains\Addresses\Entities\Address;
use App\Domains\Addresses\Infrastructure\Eloquent\Models\AddressModel;
use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use App\Domains\Addresses\ValueObjects\Coordinates;
use App\Domains\Addresses\ValueObjects\PostalCode;
use App\Shared\ValueObjects\CountryCode;
use DateTimeImmutable;

final class AddressMapper
{
    public function toEntity(AddressModel $model, string $ownerId): Address
    {
        return Address::restore(
            id: $model->uuid,
            ownerType: AddressOwnerType::from($model->addressable_type),
            ownerId: $ownerId,
            street: $model->street,
            city: $model->city,
            stateId: $model->state?->uuid,
            postalCode: $model->postal_code === null ? null : PostalCode::restore($model->postal_code),
            country: CountryCode::from($model->country_code),
            coordinates: self::coordinatesOf($model),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Address $address, int $ownerKey, ?int $stateKey): array
    {
        $coordinates = $address->coordinates();

        return [
            'uuid' => $address->id,
            'addressable_type' => $address->ownerType->value,
            'addressable_id' => $ownerKey,
            'street' => $address->street(),
            'city' => $address->city(),
            'state_id' => $stateKey,
            'postal_code' => $address->postalCode()?->value,
            'country_code' => $address->country()->value,
            'latitude' => $coordinates?->latitude,
            'longitude' => $coordinates?->longitude,
        ];
    }

    private static function coordinatesOf(AddressModel $model): ?Coordinates
    {
        if ($model->latitude === null || $model->longitude === null) {
            return null;
        }

        return Coordinates::restore((float) $model->latitude, (float) $model->longitude);
    }
}
