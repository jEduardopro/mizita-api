<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\RemoveCustomerPhotoInput;
use App\Domains\Customers\Exceptions\CustomerNotFound;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Customers\CustomerFixtures;

it('accepts the uuid of a customer', function () {
    expect(fn () => (new RemoveCustomerPhotoInput(CustomerFixtures::CUSTOMER_ID))->validate())
        ->not->toThrow(Throwable::class);
});

it('accepts a uuid however the caller cased it', function () {
    expect(fn () => (new RemoveCustomerPhotoInput(mb_strtoupper(CustomerFixtures::CUSTOMER_ID)))->validate())
        ->not->toThrow(Throwable::class);
});

it('refuses an identifier that cannot be a customer', function (string $customerId) {
    expect(fn () => (new RemoveCustomerPhotoInput($customerId))->validate())
        ->toThrow(CustomerNotFound::class);
})->with([
    'empty' => '',
    'spaces' => '   ',
    'not a uuid' => 'ada-lovelace',
    'a row number' => '7',
    'a truncated uuid' => '01930000-0000-7000-8000-0000000000c',
    'a uuid with a stray character' => '01930000-0000-7000-8000-0000000000c1x',
]);

it('refuses as something nobody has, never as something they may not touch', function () {
    $failure = null;

    try {
        (new RemoveCustomerPhotoInput('not-a-uuid'))->validate();
    } catch (CustomerNotFound $caught) {
        $failure = $caught;
    }

    expect($failure?->errorCode())->toBe('customer_not_found')
        ->and($failure?->kind())->toBe(DomainFailureKind::NotFound);
});
