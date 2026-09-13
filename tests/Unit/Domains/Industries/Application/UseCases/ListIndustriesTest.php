<?php

declare(strict_types=1);

use App\Domains\Industries\Application\Dtos\IndustryData;
use App\Domains\Industries\Application\UseCases\ListIndustries;
use App\Domains\Industries\Contracts\IndustryRepository;
use App\Domains\Industries\Entities\Industry;

/*
| Built from a mock alone: no container, no migrations, no database.
|
| Industries is a root domain, so there is deliberately no BusinessContext
| here - the catalog belongs to the platform and is the same for every caller,
| authenticated or not. A context appearing in this use case would be the bug.
*/

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

    $catalog = $this->useCase->handle();

    expect($catalog)->toHaveCount(1)
        ->and($catalog[0])->toBeInstanceOf(IndustryData::class)
        ->and($catalog[0]->id)->toBe('industry-1')
        ->and($catalog[0]->key)->toBe('barbershop')
        ->and($catalog[0]->position)->toBe(1);
});

it('keeps the order the repository returned', function () {
    // Ordering is the repository's job - active rows by position then key - and
    // the use case must not quietly re-sort what it was handed.
    $this->industries->shouldReceive('allActive')->once()->andReturn([
        anIndustry('industry-3', 'salon', 2),
        anIndustry('industry-1', 'barbershop', 1),
        anIndustry('industry-2', 'spa', 1),
    ]);

    expect(array_map(
        static fn (IndustryData $industry): string => $industry->id,
        $this->useCase->handle(),
    ))->toBe(['industry-3', 'industry-1', 'industry-2']);
});

it('returns nothing when the catalog is empty', function () {
    $this->industries->shouldReceive('allActive')->once()->andReturn([]);

    expect($this->useCase->handle())->toBe([]);
});

it('hands back a list, never an entity', function () {
    // Entities never leave the application layer.
    $this->industries->shouldReceive('allActive')->once()->andReturn([
        anIndustry('industry-1', 'barbershop', 1),
        anIndustry('industry-2', 'spa', 2),
    ]);

    $catalog = $this->useCase->handle();

    expect(array_keys($catalog))->toBe([0, 1])
        ->and($catalog)->each->toBeInstanceOf(IndustryData::class);
});

it('asks the repository for the active rows only', function () {
    // The one query it may make: a retired industry still resolves by id, but
    // it is not on offer, and this use case is the offer.
    $this->industries->shouldReceive('allActive')->once()->andReturn([]);
    $this->industries->shouldNotReceive('findById');
    $this->industries->shouldNotReceive('existsById');
    $this->industries->shouldNotReceive('isSelectable');

    $this->useCase->handle();
});
