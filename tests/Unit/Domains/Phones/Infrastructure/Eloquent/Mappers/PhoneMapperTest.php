<?php

declare(strict_types=1);

use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\Infrastructure\Eloquent\Mappers\PhoneMapper;
use App\Domains\Phones\Infrastructure\Eloquent\Models\PhoneModel;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\InvalidPhoneNumber;
use App\Shared\ValueObjects\PhoneNumberType;
use Tests\Support\PhoneNumbers;

const PHONE_MAPPER_UUID = '01930000-0000-7000-8000-0000000000c1';

const PHONE_MAPPER_OWNER_UUID = '01930000-0000-7000-8000-0000000000c2';

const PHONE_MAPPER_OWNER_KEY = 42;

function phoneMapperCreatedAt(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-01-01T12:00:00+00:00');
}

/**
 * @param  array<string, mixed>  $overrides
 */
function phoneRow(array $overrides = []): PhoneModel
{
    $model = new PhoneModel;

    $model->setRawAttributes([
        'id' => 7,
        'uuid' => PHONE_MAPPER_UUID,
        'phoneable_type' => 'business',
        'phoneable_id' => PHONE_MAPPER_OWNER_KEY,
        'country_code' => 'MX',
        'national_number' => PhoneNumbers::MX_NATIONAL_NUMBER,
        'calling_code' => '52',
        'e164' => PhoneNumbers::MX_E164,
        'number_type' => PhoneNumberType::FixedLineOrMobile->value,
        'geo_description' => PhoneNumbers::MX_GEO_DESCRIPTION,
        'timezones' => json_encode([PhoneNumbers::MX_TIMEZONE]),
        'created_at' => phoneMapperCreatedAt(),
        ...$overrides,
    ], true);

    return $model;
}

function aPhoneEntity(?PhoneOwnerType $ownerType = null): Phone
{
    return Phone::restore(
        id: PHONE_MAPPER_UUID,
        ownerType: $ownerType ?? PhoneOwnerType::Business,
        ownerId: PHONE_MAPPER_OWNER_UUID,
        number: PhoneNumbers::mexican(),
        createdAt: phoneMapperCreatedAt(),
    );
}

beforeEach(function () {
    $this->mapper = new PhoneMapper;
});

describe('entity to row', function () {
    it('writes the owner as the int key it was handed, never as the uuid', function () {
        $attributes = $this->mapper->toAttributes(aPhoneEntity(), PHONE_MAPPER_OWNER_KEY);

        expect($attributes['phoneable_id'])->toBe(PHONE_MAPPER_OWNER_KEY)
            ->and($attributes['phoneable_id'])->toBeInt()
            ->and($attributes)->not->toContain(PHONE_MAPPER_OWNER_UUID);
    });

    it('spreads the number across its seven columns', function () {
        expect($this->mapper->toAttributes(aPhoneEntity(), PHONE_MAPPER_OWNER_KEY))->toBe([
            'uuid' => PHONE_MAPPER_UUID,
            'phoneable_type' => 'business',
            'phoneable_id' => PHONE_MAPPER_OWNER_KEY,
            'country_code' => 'MX',
            'national_number' => PhoneNumbers::MX_NATIONAL_NUMBER,
            'calling_code' => 52,
            'e164' => PhoneNumbers::MX_E164,
            'number_type' => 'fixed_line_or_mobile',
            'geo_description' => PhoneNumbers::MX_GEO_DESCRIPTION,
            'timezones' => [PhoneNumbers::MX_TIMEZONE],
        ]);
    });

    it('writes the owner kind as the stored alias', function (PhoneOwnerType $ownerType, string $alias) {
        expect($this->mapper->toAttributes(aPhoneEntity($ownerType), PHONE_MAPPER_OWNER_KEY)['phoneable_type'])
            ->toBe($alias);
    })->with([
        'business' => [PhoneOwnerType::Business, 'business'],
        'staff member' => [PhoneOwnerType::StaffMember, 'staff_member'],
    ]);

    it('never writes the internal primary key', function () {
        expect($this->mapper->toAttributes(aPhoneEntity(), PHONE_MAPPER_OWNER_KEY))->not->toHaveKey('id');
    });

    it('writes a missing geographic description as null rather than an empty string', function () {
        $phone = Phone::restore(
            PHONE_MAPPER_UUID,
            PhoneOwnerType::Business,
            PHONE_MAPPER_OWNER_UUID,
            PhoneNumbers::mexican(geoDescription: null),
            phoneMapperCreatedAt(),
        );

        expect($this->mapper->toAttributes($phone, PHONE_MAPPER_OWNER_KEY)['geo_description'])->toBeNull();
    });

    it('writes the time zones as a list, so jsonb stores an array', function () {
        $phone = Phone::restore(
            PHONE_MAPPER_UUID,
            PhoneOwnerType::Business,
            PHONE_MAPPER_OWNER_UUID,
            PhoneNumbers::american(timezones: ['America/New_York', 'America/Chicago']),
            phoneMapperCreatedAt(),
        );

        $timezones = $this->mapper->toAttributes($phone, PHONE_MAPPER_OWNER_KEY)['timezones'];

        expect($timezones)->toBe(['America/New_York', 'America/Chicago'])
            ->and(array_keys($timezones))->toBe([0, 1]);
    });
});

describe('row to entity', function () {
    it('reads the owner back as the uuid it was handed, never as the column', function () {
        $phone = $this->mapper->toEntity(phoneRow(), PHONE_MAPPER_OWNER_UUID);

        expect($phone->ownerId)->toBe(PHONE_MAPPER_OWNER_UUID)
            ->and($phone->ownerId)->not->toBe((string) PHONE_MAPPER_OWNER_KEY);
    });

    it('takes the identity from the uuid column, not from the primary key', function () {
        expect($this->mapper->toEntity(phoneRow(), PHONE_MAPPER_OWNER_UUID)->id)->toBe(PHONE_MAPPER_UUID);
    });

    it('rebuilds the number from the seven columns', function () {
        $number = $this->mapper->toEntity(phoneRow(), PHONE_MAPPER_OWNER_UUID)->number();

        expect($number->country())->toBe(CountryCode::Mx)
            ->and($number->callingCode())->toBe(52)
            ->and($number->nationalNumber())->toBe(PhoneNumbers::MX_NATIONAL_NUMBER)
            ->and($number->e164())->toBe(PhoneNumbers::MX_E164)
            ->and($number->type())->toBe(PhoneNumberType::FixedLineOrMobile)
            ->and($number->geoDescription())->toBe(PhoneNumbers::MX_GEO_DESCRIPTION)
            ->and($number->timezones())->toBe([PhoneNumbers::MX_TIMEZONE]);
    });

    it('reads the owner kind back from the alias', function (string $alias, PhoneOwnerType $ownerType) {
        expect($this->mapper->toEntity(phoneRow(['phoneable_type' => $alias]), PHONE_MAPPER_OWNER_UUID)->ownerType)
            ->toBe($ownerType);
    })->with([
        'business' => ['business', PhoneOwnerType::Business],
        'staff member' => ['staff_member', PhoneOwnerType::StaffMember],
    ]);

    it('keeps the instant the row was created', function () {
        expect($this->mapper->toEntity(phoneRow(), PHONE_MAPPER_OWNER_UUID)->createdAt)
            ->toEqual(phoneMapperCreatedAt());
    });

    it('reads a row with no geographic description as a number with no place', function () {
        expect($this->mapper->toEntity(phoneRow(['geo_description' => null]), PHONE_MAPPER_OWNER_UUID)
            ->number()->geoDescription())->toBeNull();
    });

    it('refuses a row whose parts have stopped agreeing', function () {
        expect(fn () => $this->mapper->toEntity(phoneRow(['e164' => '+525599999999']), PHONE_MAPPER_OWNER_UUID))
            ->toThrow(InvalidPhoneNumber::class);
    });
});

it('survives a full round trip without losing a fact', function () {
    $phone = Phone::restore(
        id: PHONE_MAPPER_UUID,
        ownerType: PhoneOwnerType::StaffMember,
        ownerId: PHONE_MAPPER_OWNER_UUID,
        number: PhoneNumbers::american(
            type: PhoneNumberType::TollFree,
            geoDescription: null,
            timezones: ['America/New_York', 'America/Chicago', 'Pacific/Honolulu'],
        ),
        createdAt: phoneMapperCreatedAt(),
    );

    $attributes = $this->mapper->toAttributes($phone, PHONE_MAPPER_OWNER_KEY);

    $row = phoneRow([
        ...$attributes,
        'timezones' => json_encode($attributes['timezones']),
        'created_at' => phoneMapperCreatedAt(),
    ]);

    $restored = $this->mapper->toEntity($row, PHONE_MAPPER_OWNER_UUID);

    expect($restored->id)->toBe($phone->id)
        ->and($restored->ownerType)->toBe($phone->ownerType)
        ->and($restored->ownerId)->toBe($phone->ownerId)
        ->and($restored->createdAt)->toEqual($phone->createdAt)
        ->and($restored->number()->country())->toBe($phone->number()->country())
        ->and($restored->number()->callingCode())->toBe($phone->number()->callingCode())
        ->and($restored->number()->nationalNumber())->toBe($phone->number()->nationalNumber())
        ->and($restored->number()->e164())->toBe($phone->number()->e164())
        ->and($restored->number()->type())->toBe($phone->number()->type())
        ->and($restored->number()->geoDescription())->toBeNull()
        ->and($restored->number()->timezones())
        ->toBe(['America/New_York', 'America/Chicago', 'Pacific/Honolulu']);
});
