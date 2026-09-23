<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Application\Dtos\ListPublicStatesInput;
use App\Domains\PublicCatalog\Application\UseCases\ListPublicStates;
use App\Domains\PublicCatalog\Contracts\PublishedStates;
use App\Domains\PublicCatalog\Exceptions\UnsupportedPublicCountry;
use App\Domains\PublicCatalog\ValueObjects\PublicState;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\DomainFailureKind;

beforeEach(function () {
    $this->states = Mockery::mock(PublishedStates::class);

    $this->useCase = new ListPublicStates($this->states);

    $this->list = fn (array $payload = []): UseCaseResponse => $this->useCase
        ->handle(ListPublicStatesInput::fromRequest($payload));

    $this->cdmx = new PublicState('01930000-0000-7000-8000-0000000000f1', 'CMX', 'Ciudad de México');
    $this->nuevoLeon = new PublicState('01930000-0000-7000-8000-0000000000f2', 'NLE', 'Nuevo León');
});

describe('listing the states a visitor may pick', function () {
    it('answers with the states the port published, in the order it published them', function () {
        $this->states->shouldReceive('activeFor')->once()->andReturn([$this->cdmx, $this->nuevoLeon]);

        $response = ($this->list)(['country' => 'MX']);
        $states = $response->value();

        expect($response->succeeded())->toBeTrue()
            ->and($states)->toHaveCount(2)
            ->and($states[0]->id)->toBe('01930000-0000-7000-8000-0000000000f1')
            ->and($states[0]->code)->toBe('CMX')
            ->and($states[0]->name)->toBe('Ciudad de México')
            ->and($states[1]->id)->toBe('01930000-0000-7000-8000-0000000000f2')
            ->and($states[1]->code)->toBe('NLE')
            ->and($states[1]->name)->toBe('Nuevo León');
    });

    it('asks the port for the country the visitor named, resolved to the enum', function (string $sent, CountryCode $expected) {
        $country = null;

        $this->states->shouldReceive('activeFor')->once()->with(Mockery::capture($country))->andReturn([]);

        ($this->list)(['country' => $sent]);

        expect($country)->toBe($expected);
    })->with([
        'mexico' => ['MX', CountryCode::Mx],
        'united states in lowercase' => [' us ', CountryCode::Us],
    ]);

    it('asks the port for mexico when the visitor names no country', function () {
        $country = null;

        $this->states->shouldReceive('activeFor')->once()->with(Mockery::capture($country))->andReturn([]);

        ($this->list)();

        expect($country)->toBe(CountryCode::Mx);
    });

    it('answers with an empty list, still a success, for a country with no active states', function () {
        $this->states->shouldReceive('activeFor')->once()->andReturn([]);

        $response = ($this->list)(['country' => 'US']);

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBe([]);
    });

    it('sends every state id as a uuid, never an internal key', function () {
        $this->states->shouldReceive('activeFor')->once()->andReturn([$this->cdmx, $this->nuevoLeon]);

        $ids = array_map(static fn (PublicState $state): string => $state->id, ($this->list)()->value());

        expect(array_filter($ids, is_numeric(...)))->toBe([]);
    });

    it('carries no warning on a plain listing', function () {
        $this->states->shouldReceive('activeFor')->once()->andReturn([$this->cdmx]);

        expect(($this->list)()->warnings())->toBe([]);
    });
});

describe('a country the platform does not operate in', function () {
    it('answers with an invalid refusal coded unsupported_country instead of throwing it', function () {
        $this->states->shouldNotReceive('activeFor');

        $response = ($this->list)(['country' => 'BR']);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('unsupported_country')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($response->error()->cause())->toBeInstanceOf(UnsupportedPublicCountry::class);
    });

    it('never asks the port for anything', function () {
        $this->states->shouldNotReceive('activeFor');

        expect(($this->list)(['country' => 'XX'])->failed())->toBeTrue();
    });

    it('validates a DTO built without fromRequest too', function () {
        $this->states->shouldNotReceive('activeFor');

        $response = $this->useCase->handle(new ListPublicStatesInput('mx'));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('unsupported_country');
    });

    it('never hands an empty list back as a success', function () {
        $this->states->shouldNotReceive('activeFor');

        expect(fn () => ($this->list)(['country' => 'BR'])->value())->toThrow(UnsupportedPublicCountry::class);
    });
});

describe('what is not a refusal', function () {
    it('lets an infrastructure error out, because that is a bug and not a verdict', function () {
        $bug = new RuntimeException('the states table is gone');

        $this->states->shouldReceive('activeFor')->once()->andThrow($bug);

        expect(fn () => ($this->list)())->toThrow($bug);
    });
});
