<?php

declare(strict_types=1);

namespace App\Domains\Phones\Infrastructure\Eloquent\Mappers;

use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\Infrastructure\Eloquent\Models\PhoneModel;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\PhoneNumber;
use App\Shared\ValueObjects\PhoneNumberType;
use DateTimeImmutable;

/**
 * A PhoneNumber spreads across seven columns because each is a fact the platform
 * checked rather than one it can recompute. PhoneNumber::of() re-checks that the
 * three that must agree still do, so a row assembled by hand cannot come back as
 * a number that would be dialled differently from the one stored.
 *
 * The owner uuid travels as a parameter rather than a column: the table holds
 * the owner's int primary key, and resolving the two is the repository's job.
 */
final class PhoneMapper
{
    public function toEntity(PhoneModel $model, string $ownerId): Phone
    {
        return Phone::restore(
            id: $model->uuid,
            ownerType: PhoneOwnerType::from($model->phoneable_type),
            ownerId: $ownerId,
            number: PhoneNumber::of(
                country: CountryCode::from($model->country_code),
                callingCode: $model->calling_code,
                nationalNumber: $model->national_number,
                e164: $model->e164,
                type: PhoneNumberType::from($model->number_type),
                geoDescription: $model->geo_description,
                timezones: array_values($model->timezones),
            ),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Phone $phone, int $ownerKey): array
    {
        $number = $phone->number();

        return [
            'uuid' => $phone->id,
            'phoneable_type' => $phone->ownerType->value,
            'phoneable_id' => $ownerKey,
            'country_code' => $number->country()->value,
            'national_number' => $number->nationalNumber(),
            'calling_code' => $number->callingCode(),
            'e164' => $number->e164(),
            'number_type' => $number->type()->value,
            'geo_description' => $number->geoDescription(),
            'timezones' => $number->timezones(),
        ];
    }
}
