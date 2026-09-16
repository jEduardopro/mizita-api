<?php

declare(strict_types=1);

use App\Domains\Addresses\Application\Dtos\ListStatesInput;
use App\Domains\Addresses\Application\Dtos\StateData;
use App\Domains\Addresses\Application\UseCases\ListStates;
use App\Domains\Addresses\Contracts\StateCatalog;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Addresses\AddressFixtures;

beforeEach(function () {
    $this->states = Mockery::mock(StateCatalog::class);
    $this->useCase = new ListStates($this->states);
});

it('returns the catalogue as data, field by field', function () {
    $this->states->shouldReceive('allActiveFor')->once()->andReturn([
        AddressFixtures::state(),
    ]);

    $catalogue = $this->useCase->handle(ListStatesInput::fromRequest([]))->value();

    expect($catalogue)->toHaveCount(1)
        ->and($catalogue[0])->toBeInstanceOf(StateData::class)
        ->and($catalogue[0]->id)->toBe(AddressFixtures::STATE_ID)
        ->and($catalogue[0]->countryCode)->toBe('MX')
        ->and($catalogue[0]->code)->toBe('CMX')
        ->and($catalogue[0]->name)->toBe('Ciudad de México')
        ->and($catalogue[0]->position)->toBe(9);
});

it('identifies a state by its uuid, never by the row the address joins on', function () {
    $this->states->shouldReceive('allActiveFor')->once()->andReturn([AddressFixtures::state()]);

    expect($this->useCase->handle(ListStatesInput::fromRequest([]))->value()[0]->id)
        ->toBe(AddressFixtures::STATE_ID)
        ->not->toBe((string) AddressFixtures::STATE_KEY);
});

it('asks the catalogue for the country the caller named', function () {
    $this->states->shouldReceive('allActiveFor')->once()->with(CountryCode::Us)->andReturn([]);

    $this->useCase->handle(ListStatesInput::fromRequest(['country' => 'us']));
});

it('asks for Mexico when the caller named no country', function () {
    $this->states->shouldReceive('allActiveFor')->once()->with(CountryCode::Mx)->andReturn([]);

    $this->useCase->handle(ListStatesInput::fromRequest([]));
});

it('keeps the order the catalogue returned, because position is the catalogue knowledge', function () {
    $this->states->shouldReceive('allActiveFor')->once()->andReturn([
        AddressFixtures::state(id: AddressFixtures::SECOND_STATE_ID, code: 'AGU', name: 'Aguascalientes', position: 1),
        AddressFixtures::state(),
    ]);

    expect(array_map(
        static fn (StateData $state): string => $state->code,
        $this->useCase->handle(ListStatesInput::fromRequest([]))->value(),
    ))->toBe(['AGU', 'CMX']);
});

it('hands back a plain list, never an entity', function () {
    $this->states->shouldReceive('allActiveFor')->once()->andReturn([
        AddressFixtures::state(),
        AddressFixtures::state(id: AddressFixtures::SECOND_STATE_ID, code: 'AGU'),
    ]);

    $catalogue = $this->useCase->handle(ListStatesInput::fromRequest([]))->value();

    expect(array_keys($catalogue))->toBe([0, 1])
        ->and($catalogue)->each->toBeInstanceOf(StateData::class);
});

it('reports success on an empty catalogue, because emptiness is not a refusal', function () {
    $this->states->shouldReceive('allActiveFor')->once()->andReturn([]);

    $response = $this->useCase->handle(ListStatesInput::fromRequest([]));

    expect($response)->toBeInstanceOf(UseCaseResponse::class)
        ->and($response->succeeded())->toBeTrue()
        ->and($response->value())->toBe([])
        ->and($response->warnings())->toBe([]);
});

it('refuses a country the platform does not operate in', function () {
    $this->states->shouldNotReceive('allActiveFor');

    $response = $this->useCase->handle(new ListStatesInput('ZZ'));

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('unsupported_country')
        ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid);
});

it('lets a storage failure escape rather than dressing it as a refusal', function () {
    $this->states->shouldReceive('allActiveFor')->once()
        ->andThrow(new RuntimeException('SQLSTATE[08006] connection failure'));

    expect(fn () => $this->useCase->handle(ListStatesInput::fromRequest([])))
        ->toThrow(RuntimeException::class, 'SQLSTATE[08006] connection failure');
});
