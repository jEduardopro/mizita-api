<?php

declare(strict_types=1);

use App\Domains\Addresses\Application\UseCases\ListStates;
use App\Domains\Addresses\Contracts\StateCatalog;
use App\Domains\PublicCatalog\Infrastructure\Gateways\AddressesPublishedStates;
use App\Domains\PublicCatalog\ValueObjects\PublicState;
use App\Shared\ValueObjects\CountryCode;
use Tests\Support\Addresses\AddressFixtures;

beforeEach(function () {
    $this->catalog = Mockery::mock(StateCatalog::class);

    $this->gateway = new AddressesPublishedStates(new ListStates($this->catalog));
});

describe('the states a visitor may pick', function () {
    it('publishes the id, the code and the name of every active state', function () {
        $this->catalog->shouldReceive('allActiveFor')->once()->andReturn([
            AddressFixtures::state(),
        ]);

        $states = $this->gateway->activeFor(CountryCode::Mx);

        expect($states)->toHaveCount(1)
            ->and($states[0])->toBeInstanceOf(PublicState::class)
            ->and($states[0]->id)->toBe(AddressFixtures::STATE_ID)
            ->and($states[0]->code)->toBe('CMX')
            ->and($states[0]->name)->toBe('Ciudad de México');
    });

    it('keeps the order the catalog listed them in', function () {
        $this->catalog->shouldReceive('allActiveFor')->once()->andReturn([
            AddressFixtures::state(id: AddressFixtures::SECOND_STATE_ID, code: 'NLE', name: 'Nuevo León', position: 19),
            AddressFixtures::state(),
        ]);

        $states = $this->gateway->activeFor(CountryCode::Mx);

        expect(array_map(static fn (PublicState $state): string => $state->code, $states))->toBe(['NLE', 'CMX'])
            ->and(array_keys($states))->toBe([0, 1]);
    });

    it('asks the catalog for the country it was given', function (CountryCode $asked) {
        $country = null;

        $this->catalog->shouldReceive('allActiveFor')->once()->with(Mockery::capture($country))->andReturn([]);

        $this->gateway->activeFor($asked);

        expect($country)->toBe($asked);
    })->with([
        'mexico' => [CountryCode::Mx],
        'united states' => [CountryCode::Us],
    ]);

    it('publishes nothing for a country with no active states', function () {
        $this->catalog->shouldReceive('allActiveFor')->once()->andReturn([]);

        expect($this->gateway->activeFor(CountryCode::Us))->toBe([]);
    });

    it('keeps accents in the state name', function () {
        $this->catalog->shouldReceive('allActiveFor')->once()->andReturn([
            AddressFixtures::state(code: 'MIC', name: 'Michoacán de Ocampo'),
        ]);

        expect($this->gateway->activeFor(CountryCode::Mx)[0]->name)->toBe('Michoacán de Ocampo');
    });

    it('publishes the state uuid, never an internal key', function () {
        $this->catalog->shouldReceive('allActiveFor')->once()->andReturn([
            AddressFixtures::state(),
            AddressFixtures::state(id: AddressFixtures::SECOND_STATE_ID, code: 'NLE', name: 'Nuevo León'),
        ]);

        $ids = array_map(
            static fn (PublicState $state): string => $state->id,
            $this->gateway->activeFor(CountryCode::Mx),
        );

        expect($ids)->toBe([AddressFixtures::STATE_ID, AddressFixtures::SECOND_STATE_ID])
            ->and(array_filter($ids, is_numeric(...)))->toBe([]);
    });

    it('leaves the country and the position of each state behind', function () {
        $fields = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(PublicState::class))->getProperties(),
        );

        expect($fields)->toBe(['id', 'code', 'name']);
    });
});
