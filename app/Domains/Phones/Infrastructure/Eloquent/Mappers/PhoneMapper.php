<?php

declare(strict_types=1);

namespace App\Domains\Phones\Infrastructure\Eloquent\Mappers;

use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\Infrastructure\Eloquent\Models\PhoneModel;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\PhoneNumber;
use DateTimeImmutable;

/**
 * Translates between the persistence model and the domain entity. Only the
 * repository adapter uses it.
 *
 * A PhoneNumber becomes the country_code / national_number column pair. The
 * dial code is never stored: it is a fact about the country, and CountryCode
 * is the one place that knows it.
 */
final class PhoneMapper
{
    public function toEntity(PhoneModel $model): Phone
    {
        return Phone::restore(
            // The uuid is the domain identity; the int primary key stays here.
            id: $model->uuid,
            ownerType: PhoneOwnerType::from($model->phoneable_type),
            ownerId: $model->phoneable_id,
            number: PhoneNumber::fromParts(
                CountryCode::from($model->country_code),
                $model->national_number,
            ),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Phone $phone): array
    {
        return [
            'uuid' => $phone->id,
            'phoneable_type' => $phone->ownerType->value,
            'phoneable_id' => $phone->ownerId,
            'country_code' => $phone->number()->country()->value,
            'national_number' => $phone->number()->nationalNumber(),
        ];
    }
}
