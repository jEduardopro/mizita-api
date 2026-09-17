<?php

declare(strict_types=1);

use App\Domains\Customers\ValueObjects\CustomerAddressSnapshot;

it('carries the place a customer is at, in the shape this domain asks its neighbour for', function () {
    $snapshot = new CustomerAddressSnapshot(
        street: 'Avenida Insurgentes Sur 1602',
        city: 'Ciudad de México',
        stateId: '01930000-0000-7000-8000-0000000000e1',
        postalCode: '03940',
        countryCode: 'MX',
    );

    expect($snapshot->street)->toBe('Avenida Insurgentes Sur 1602')
        ->and($snapshot->city)->toBe('Ciudad de México')
        ->and($snapshot->stateId)->toBe('01930000-0000-7000-8000-0000000000e1')
        ->and($snapshot->postalCode)->toBe('03940')
        ->and($snapshot->countryCode)->toBe('MX');
});

it('names the state by the uuid, never by a row number', function () {
    $snapshot = new CustomerAddressSnapshot(
        street: 'Avenida Insurgentes Sur 1602',
        city: null,
        stateId: '01930000-0000-7000-8000-0000000000e1',
        postalCode: null,
        countryCode: 'MX',
    );

    expect($snapshot->stateId)->toBe('01930000-0000-7000-8000-0000000000e1')
        ->and($snapshot->stateId)->not->toBe('7');
});

it('carries an address that names a street and nothing around it', function () {
    $snapshot = new CustomerAddressSnapshot(
        street: 'Avenida Insurgentes Sur 1602',
        city: null,
        stateId: null,
        postalCode: null,
        countryCode: 'MX',
    );

    expect($snapshot->city)->toBeNull()
        ->and($snapshot->stateId)->toBeNull()
        ->and($snapshot->postalCode)->toBeNull();
});

it('can hold a blank street, which is how a caller says the address is to be left alone', function () {
    $snapshot = new CustomerAddressSnapshot(
        street: '',
        city: null,
        stateId: null,
        postalCode: null,
        countryCode: '',
    );

    expect($snapshot->street)->toBe('')
        ->and($snapshot->countryCode)->toBe('');
});

it('holds what it was handed without normalising it, because the address that owns those rules trims', function () {
    $snapshot = new CustomerAddressSnapshot(
        street: '  Avenida Insurgentes Sur 1602  ',
        city: '  Ciudad de México  ',
        stateId: null,
        postalCode: '  03940  ',
        countryCode: '  mx  ',
    );

    expect($snapshot->street)->toBe('  Avenida Insurgentes Sur 1602  ')
        ->and($snapshot->city)->toBe('  Ciudad de México  ')
        ->and($snapshot->postalCode)->toBe('  03940  ')
        ->and($snapshot->countryCode)->toBe('  mx  ');
});
