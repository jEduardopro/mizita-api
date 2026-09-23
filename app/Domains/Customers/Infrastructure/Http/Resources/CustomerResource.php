<?php

declare(strict_types=1);

namespace App\Domains\Customers\Infrastructure\Http\Resources;

use App\Domains\Customers\Application\Dtos\CustomerAddressData;
use App\Domains\Customers\Application\Dtos\CustomerData;
use App\Domains\Customers\ValueObjects\BirthDate;
use App\Shared\ValueObjects\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CustomerData $resource
 */
final class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'phone' => self::describePhone($this->resource->phone),
            'birth_date' => $this->resource->birthDate?->format(BirthDate::FORMAT),
            'notes' => $this->resource->notes,
            'address' => self::describeAddress($this->resource->address),
            'photo_url' => $this->resource->photoUrl,
            'created_at' => $this->resource->createdAt->format(DATE_ATOM),
        ];
    }

    /**
     * @return array{country_code: string, national_number: string}|null
     */
    private static function describePhone(?PhoneNumber $phone): ?array
    {
        if ($phone === null) {
            return null;
        }

        return [
            'country_code' => $phone->country()->value,
            'national_number' => $phone->nationalNumber(),
        ];
    }

    /**
     * @return array{street: string, city: string|null, state_id: string|null, state_name: string|null, postal_code: string|null, country_code: string}|null
     */
    private static function describeAddress(?CustomerAddressData $address): ?array
    {
        if ($address === null) {
            return null;
        }

        return [
            'street' => $address->street,
            'city' => $address->city,
            'state_id' => $address->stateId,
            'state_name' => $address->stateName,
            'postal_code' => $address->postalCode,
            'country_code' => $address->countryCode,
        ];
    }
}
