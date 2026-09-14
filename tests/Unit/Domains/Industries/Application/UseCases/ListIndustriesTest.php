<?php

declare(strict_types=1);

use App\Domains\Industries\Application\Dtos\IndustryData;
use App\Domains\Industries\Application\UseCases\ListIndustries;
use App\Domains\Industries\Contracts\IndustryRepository;
use App\Domains\Industries\Entities\Industry;
use App\Domains\Industries\Exceptions\InvalidIndustryKey;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

function anIndustry(string $id, string $key, int $position): Industry
{
    return Industry::restore(
        id: $id,
        key: $key,
        position: $position,
        active: true,
        createdAt: new DateTimeImmutable('2026-01-01T12:00:00+00:00'),
    );
}

beforeEach(function () {
    $this->industries = Mockery::mock(IndustryRepository::class);
    $this->useCase = new ListIndustries($this->industries);
});

it('returns the active catalog as data, field by field', function () {
    $this->industries->shouldReceive('allActive')->once()->andReturn([
        anIndustry('industry-1', 'barbershop', 1),
    ]);

    $catalog = $this->useCase->handle()->value();

    expect($catalog)->toHaveCount(1)
        ->and($catalog[0])->toBeInstanceOf(IndustryData::class)
        ->and($catalog[0]->id)->toBe('industry-1')
        ->and($catalog[0]->key)->toBe('barbershop')
        ->and($catalog[0]->position)->toBe(1);
});

it('keeps the order the repository returned', function () {
    $this->industries->shouldReceive('allActive')->once()->andReturn([
        anIndustry('industry-3', 'salon', 2),
        anIndustry('industry-1', 'barbershop', 1),
        anIndustry('industry-2', 'spa', 1),
    ]);

    expect(array_map(
        static fn (IndustryData $industry): string => $industry->id,
        $this->useCase->handle()->value(),
    ))->toBe(['industry-3', 'industry-1', 'industry-2']);
});

it('returns nothing when the catalog is empty', function () {
    $this->industries->shouldReceive('allActive')->once()->andReturn([]);

    expect($this->useCase->handle()->value())->toBe([]);
});

it('hands back a list, never an entity', function () {
    $this->industries->shouldReceive('allActive')->once()->andReturn([
        anIndustry('industry-1', 'barbershop', 1),
        anIndustry('industry-2', 'spa', 2),
    ]);

    $catalog = $this->useCase->handle()->value();

    expect(array_keys($catalog))->toBe([0, 1])
        ->and($catalog)->each->toBeInstanceOf(IndustryData::class);
});

it('asks the repository for the active rows only', function () {
    $this->industries->shouldReceive('allActive')->once()->andReturn([]);
    $this->industries->shouldNotReceive('findById');
    $this->industries->shouldNotReceive('existsById');
    $this->industries->shouldNotReceive('isSelectable');

    $this->useCase->handle();
});

describe('the response it hands back', function () {
    it('reports success and carries no warning', function () {
        $this->industries->shouldReceive('allActive')->once()->andReturn([
            anIndustry('industry-1', 'barbershop', 1),
        ]);

        $response = $this->useCase->handle();

        expect($response)->toBeInstanceOf(UseCaseResponse::class)
            ->and($response->succeeded())->toBeTrue()
            ->and($response->failed())->toBeFalse()
            ->and($response->warnings())->toBe([]);
    });

    it('reports success even when the catalog is empty, because emptiness is not a refusal', function () {
        $this->industries->shouldReceive('allActive')->once()->andReturn([]);

        expect($this->useCase->handle()->succeeded())->toBeTrue();
    });

    it('lets a corrupt row escape as a server error instead of a refusal the client could read', function () {
        $this->industries->shouldReceive('allActive')->once()->andThrow(InvalidIndustryKey::empty());

        expect(fn () => $this->useCase->handle())
            ->toThrow(InvalidIndustryKey::class, 'An industry key cannot be empty.')
            ->and(is_a(InvalidIndustryKey::class, DomainFailure::class, true))->toBeFalse();
    });

    it('lets a storage failure escape rather than dressing it as a refusal', function () {
        $this->industries->shouldReceive('allActive')->once()
            ->andThrow(new RuntimeException('SQLSTATE[08006] connection failure'));

        expect(fn () => $this->useCase->handle())
            ->toThrow(RuntimeException::class, 'SQLSTATE[08006] connection failure');
    });
});
