<?php

declare(strict_types=1);

use App\Domains\Addresses\Application\Dtos\StateData;
use App\Domains\Addresses\Infrastructure\Http\Resources\StateResource;
use Tests\Support\Addresses\AddressFixtures;
use Tests\TestCase;

uses(TestCase::class);

function stateResourceData(
    string $id = AddressFixtures::STATE_ID,
    string $countryCode = 'MX',
    string $code = 'CMX',
    string $name = 'Ciudad de México',
    int $position = 9,
): StateData {
    return new StateData(
        id: $id,
        countryCode: $countryCode,
        code: $code,
        name: $name,
        position: $position,
    );
}

/**
 * @return array<string, mixed>
 */
function serializedState(StateData $state): array
{
    return (array) StateResource::make($state)->response()->getData(true)['data'];
}

it('serializes exactly the five fields the picker needs', function () {
    expect(serializedState(stateResourceData()))->toBe([
        'id' => AddressFixtures::STATE_ID,
        'country_code' => 'MX',
        'code' => 'CMX',
        'name' => 'Ciudad de México',
        'position' => 9,
    ]);
});

it('sends the uuid as the identity, never the row the addresses join on', function () {
    expect(serializedState(stateResourceData())['id'])
        ->toBe(AddressFixtures::STATE_ID)
        ->not->toBe(AddressFixtures::STATE_KEY);
});

it('keeps the catalogue bookkeeping off the wire', function () {
    expect(serializedState(stateResourceData()))->not->toHaveKey('active')
        ->and(serializedState(stateResourceData()))->not->toHaveKey('created_at')
        ->and(serializedState(stateResourceData()))->not->toHaveKey('state_id');
});

it('sends the proper noun as the table stores it, accents included', function () {
    expect(serializedState(stateResourceData(name: 'Nuevo León'))['name'])->toBe('Nuevo León');
});

it('sends the ordering as a number the client can sort on', function () {
    expect(serializedState(stateResourceData(position: 19))['position'])->toBeInt()->toBe(19);
});
