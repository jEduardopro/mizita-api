<?php

declare(strict_types=1);

use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\PhoneNumber;

/*
| Pure PHP: the entity is built by hand with a fixed instant. The rules about
| what a number may look like belong to PhoneNumber and are deliberately not
| re-checked here - the entity takes one already built.
*/

function phoneCreatedAt(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-01-01T12:00:00+00:00');
}

function aPhoneNumber(string $nationalNumber = '5512345678'): PhoneNumber
{
    return PhoneNumber::fromParts(CountryCode::Mx, $nationalNumber);
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
    // One phone per owner: a change moves the record, it is never a second row,
    // so neither the id nor the owner may move with the number.
    $phone = Phone::create('phone-1', PhoneOwnerType::Business, 'business-1', aPhoneNumber(), phoneCreatedAt());

    $phone->changeNumber(PhoneNumber::fromParts(CountryCode::Us, '4155550100'));

    expect($phone->number()->e164())->toBe('+14155550100')
        ->and($phone->id)->toBe('phone-1')
        ->and($phone->ownerType)->toBe(PhoneOwnerType::Business)
        ->and($phone->ownerId)->toBe('business-1')
        ->and($phone->createdAt)->toEqual(phoneCreatedAt());
});

it('accepts a change to the number it already holds', function () {
    // Re-submitting the same number is an ordinary thing for a form to do, and
    // it is not a conflict.
    $phone = Phone::create('phone-1', PhoneOwnerType::Business, 'business-1', aPhoneNumber(), phoneCreatedAt());

    $phone->changeNumber(aPhoneNumber());

    expect($phone->number()->equals(aPhoneNumber()))->toBeTrue();
});
