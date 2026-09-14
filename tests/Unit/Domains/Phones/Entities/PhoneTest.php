<?php

declare(strict_types=1);

use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\ValueObjects\PhoneNumber;
use App\Shared\ValueObjects\PhoneNumberType;
use Tests\Support\PhoneNumbers;

function phoneCreatedAt(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-01-01T12:00:00+00:00');
}

function aPhoneNumber(string $nationalNumber = PhoneNumbers::MX_NATIONAL_NUMBER): PhoneNumber
{
    return PhoneNumbers::mexican($nationalNumber);
}

it('creates a phone for the owner it was given', function () {
    $phone = Phone::create(
        id: 'phone-1',
        ownerType: PhoneOwnerType::Business,
        ownerId: 'business-1',
        number: aPhoneNumber(),
        now: phoneCreatedAt(),
    );

    expect($phone->id)->toBe('phone-1')
        ->and($phone->ownerType)->toBe(PhoneOwnerType::Business)
        ->and($phone->ownerId)->toBe('business-1')
        ->and($phone->number()->equals(aPhoneNumber()))->toBeTrue()
        ->and($phone->createdAt)->toEqual(phoneCreatedAt());
});

it('holds the number whole, metadata included, instead of taking it apart', function () {
    $number = PhoneNumbers::american(
        type: PhoneNumberType::TollFree,
        geoDescription: null,
        timezones: ['America/New_York', 'Pacific/Honolulu'],
    );

    $phone = Phone::create('phone-1', PhoneOwnerType::Business, 'business-1', $number, phoneCreatedAt());

    expect($phone->number())->toBe($number);
});

it('creates a phone for any kind of owner', function (PhoneOwnerType $ownerType) {
    expect(Phone::create('phone-1', $ownerType, 'owner-1', aPhoneNumber(), phoneCreatedAt())->ownerType)
        ->toBe($ownerType);
})->with([
    'business' => PhoneOwnerType::Business,
    'staff member' => PhoneOwnerType::StaffMember,
    'customer' => PhoneOwnerType::Customer,
]);

it('rehydrates a phone exactly as it was stored', function () {
    $phone = Phone::restore(
        id: 'phone-1',
        ownerType: PhoneOwnerType::Customer,
        ownerId: 'customer-1',
        number: aPhoneNumber('7654321'),
        createdAt: phoneCreatedAt(),
    );

    expect($phone->id)->toBe('phone-1')
        ->and($phone->ownerType)->toBe(PhoneOwnerType::Customer)
        ->and($phone->ownerId)->toBe('customer-1')
        ->and($phone->number()->nationalNumber())->toBe('7654321')
        ->and($phone->createdAt)->toEqual(phoneCreatedAt());
});

it('reaches a new number on the same record', function () {
    $phone = Phone::create('phone-1', PhoneOwnerType::Business, 'business-1', aPhoneNumber(), phoneCreatedAt());

    $phone->changeNumber(PhoneNumbers::american());

    expect($phone->number()->e164())->toBe(PhoneNumbers::US_E164)
        ->and($phone->id)->toBe('phone-1')
        ->and($phone->ownerType)->toBe(PhoneOwnerType::Business)
        ->and($phone->ownerId)->toBe('business-1')
        ->and($phone->createdAt)->toEqual(phoneCreatedAt());
});

it('accepts a change to the number it already holds', function () {
    $phone = Phone::create('phone-1', PhoneOwnerType::Business, 'business-1', aPhoneNumber(), phoneCreatedAt());

    $phone->changeNumber(aPhoneNumber());

    expect($phone->number()->equals(aPhoneNumber()))->toBeTrue();
});
