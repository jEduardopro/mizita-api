<?php

declare(strict_types=1);

use App\Shared\ValueObjects\CountryCode;
use Tests\Support\Addresses\AddressFixtures;

it('restores the catalogue row as the entity the use case reads', function () {
    $state = AddressFixtures::state();

    expect($state->id)->toBe(AddressFixtures::STATE_ID)
        ->and($state->country())->toBe(CountryCode::Mx)
        ->and($state->code())->toBe('CMX')
        ->and($state->name())->toBe('Ciudad de México')
        ->and($state->position())->toBe(9)
        ->and($state->isActive())->toBeTrue();
});

it('is identified by the uuid, never by the row number the catalogue joins on', function () {
    expect(AddressFixtures::state()->id)->toBe(AddressFixtures::STATE_ID)
        ->and(AddressFixtures::state()->id)->not->toBe((string) AddressFixtures::STATE_KEY);
});

it('keeps the proper noun it was seeded with, accents and all', function () {
    expect(AddressFixtures::state(name: 'Nuevo León')->name())->toBe('Nuevo León');
});

it('reports a retired state as inactive without being deleted', function () {
    expect(AddressFixtures::state(active: false)->isActive())->toBeFalse();
});

it('belongs to whichever country the catalogue lists it under', function () {
    expect(AddressFixtures::state(country: CountryCode::Us, code: 'CA', name: 'California')->country())
        ->toBe(CountryCode::Us);
});
