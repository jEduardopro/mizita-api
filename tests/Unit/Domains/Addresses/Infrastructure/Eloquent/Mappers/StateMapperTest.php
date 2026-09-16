<?php

declare(strict_types=1);

use App\Domains\Addresses\Infrastructure\Eloquent\Mappers\StateMapper;
use App\Domains\Addresses\Infrastructure\Eloquent\Models\StateModel;
use App\Shared\ValueObjects\CountryCode;
use Tests\Support\Addresses\AddressFixtures;

/**
 * @param  array<string, mixed>  $overrides
 */
function catalogueRow(array $overrides = []): StateModel
{
    $state = new StateModel;

    $state->setRawAttributes([
        'id' => AddressFixtures::STATE_KEY,
        'uuid' => AddressFixtures::STATE_ID,
        'country_code' => 'MX',
        'code' => 'CMX',
        'name' => 'Ciudad de México',
        'position' => 9,
        'active' => true,
        ...$overrides,
    ], true);

    return $state;
}

beforeEach(function () {
    $this->mapper = new StateMapper;
});

it('rebuilds the catalogue entry from the row', function () {
    $state = $this->mapper->toEntity(catalogueRow());

    expect($state->id)->toBe(AddressFixtures::STATE_ID)
        ->and($state->country())->toBe(CountryCode::Mx)
        ->and($state->code())->toBe('CMX')
        ->and($state->name())->toBe('Ciudad de México')
        ->and($state->position())->toBe(9)
        ->and($state->isActive())->toBeTrue();
});

it('takes the identity from the uuid column, not from the primary key', function () {
    expect($this->mapper->toEntity(catalogueRow())->id)
        ->toBe(AddressFixtures::STATE_ID)
        ->not->toBe((string) AddressFixtures::STATE_KEY);
});

it('reads the country column back as the enum the domain speaks', function (string $stored, CountryCode $country) {
    expect($this->mapper->toEntity(catalogueRow(['country_code' => $stored]))->country())->toBe($country);
})->with([
    'Mexico' => ['MX', CountryCode::Mx],
    'the United States' => ['US', CountryCode::Us],
]);

it('reads the flags back as the types the entity declares, not as the strings a driver returns', function () {
    $state = $this->mapper->toEntity(catalogueRow(['position' => '3', 'active' => '0']));

    expect($state->position())->toBeInt()->toBe(3)
        ->and($state->isActive())->toBeFalse();
});

it('keeps the accents of a proper noun the table stores', function () {
    expect($this->mapper->toEntity(catalogueRow(['name' => 'Nuevo León']))->name())->toBe('Nuevo León');
});

it('fails loudly on a row naming a country the platform does not operate in', function () {
    expect(fn () => $this->mapper->toEntity(catalogueRow(['country_code' => 'ES'])))->toThrow(ValueError::class);
});
