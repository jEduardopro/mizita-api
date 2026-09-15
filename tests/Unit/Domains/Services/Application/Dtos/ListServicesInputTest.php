<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\ListServicesInput;
use App\Domains\Services\Exceptions\InvalidServiceSearch;
use App\Domains\Services\ValueObjects\ServiceSort;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SortDirection;

describe('reading a query string', function () {
    it('assembles itself from a well formed query string', function () {
        $input = ListServicesInput::fromRequest([
            'search' => 'corte',
            'sort' => 'price',
            'direction' => 'desc',
            'page' => 3,
            'per_page' => 25,
        ]);

        expect($input->search)->toBe('corte')
            ->and($input->sort)->toBe('price')
            ->and($input->direction)->toBe('desc')
            ->and($input->page)->toBe(3)
            ->and($input->perPage)->toBe(25);
    });

    it('survives a query string with every key missing', function () {
        $input = ListServicesInput::fromRequest([]);

        expect($input->search)->toBeNull()
            ->and($input->sort)->toBeNull()
            ->and($input->direction)->toBeNull()
            ->and($input->page)->toBeNull()
            ->and($input->perPage)->toBeNull();
    });

    it('reads the numbers a query string carries as strings', function () {
        $input = ListServicesInput::fromRequest(['page' => '2', 'per_page' => '50']);

        expect($input->page)->toBe(2)
            ->and($input->perPage)->toBe(50);
    });

    it('reads a wrongly typed value as none', function (array $payload, string $field) {
        expect(ListServicesInput::fromRequest($payload)->{$field})->toBeNull();
    })->with([
        'search as an array' => [['search' => ['corte']], 'search'],
        'sort as an array' => [['sort' => ['name']], 'sort'],
        'page as a word' => [['page' => 'first'], 'page'],
        'page as an array' => [['page' => [2]], 'page'],
        'per page as a word' => [['per_page' => 'all'], 'perPage'],
    ]);
});

describe('validating', function () {
    it('accepts a query string every rule agrees with', function () {
        expect(fn () => ListServicesInput::fromRequest(['search' => 'corte'])->validate())
            ->not->toThrow(Throwable::class);
    });

    it('accepts an empty query string, because a list needs no arguments', function () {
        expect(fn () => ListServicesInput::fromRequest([])->validate())->not->toThrow(Throwable::class);
    });

    it('accepts a search term as long as it serves', function () {
        expect(fn () => ListServicesInput::fromRequest([
            'search' => str_repeat('a', ListServicesInput::MAXIMUM_SEARCH_LENGTH),
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('refuses a search term longer than it serves', function () {
        expect(fn () => ListServicesInput::fromRequest([
            'search' => str_repeat('a', ListServicesInput::MAXIMUM_SEARCH_LENGTH + 1),
        ])->validate())->toThrow(InvalidServiceSearch::class);
    });

    it('measures a search term after trimming it', function () {
        expect(fn () => ListServicesInput::fromRequest([
            'search' => '   '.str_repeat('a', ListServicesInput::MAXIMUM_SEARCH_LENGTH).'   ',
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('never refuses a navigation value, however absurd', function () {
        expect(fn () => ListServicesInput::fromRequest([
            'page' => -9999,
            'per_page' => 9999,
            'sort' => 'whatever',
            'direction' => 'sideways',
        ])->validate())->not->toThrow(Throwable::class);
    });
});

describe('turning itself into a query', function () {
    it('carries the search, the sort, the direction and the pagination it was given', function () {
        $query = ListServicesInput::fromRequest([
            'search' => 'corte',
            'sort' => 'created_at',
            'direction' => 'desc',
            'page' => 2,
            'per_page' => 25,
        ])->toQuery();

        expect($query->search)->toBe('corte')
            ->and($query->sort)->toBe(ServiceSort::CreatedAt)
            ->and($query->direction)->toBe(SortDirection::Descending)
            ->and($query->pagination->page)->toBe(2)
            ->and($query->pagination->perPage)->toBe(25);
    });

    it('falls back to a sort it serves instead of refusing an unknown one', function (?string $sort) {
        expect(ListServicesInput::fromRequest(['sort' => $sort])->toQuery()->sort)
            ->toBe(ServiceSort::Name);
    })->with([
        'missing' => null,
        'unknown' => 'whatever',
        'a column it does not expose' => 'business_id',
        'uppercase' => 'NAME',
        'empty' => '',
    ]);

    it('falls back to ascending instead of refusing an unknown direction', function (?string $direction) {
        expect(ListServicesInput::fromRequest(['direction' => $direction])->toQuery()->direction)
            ->toBe(SortDirection::Ascending);
    })->with(['missing' => null, 'unknown' => 'sideways', 'uppercase' => 'DESC', 'empty' => '']);

    it('clamps the pagination instead of refusing it', function () {
        $query = ListServicesInput::fromRequest(['page' => 0, 'per_page' => 9999])->toQuery();

        expect($query->pagination->page)->toBe(1)
            ->and($query->pagination->perPage)->toBe(Pagination::MAXIMUM_PER_PAGE);
    });

    it('paginates by default when nothing was asked for', function () {
        $query = ListServicesInput::fromRequest([])->toQuery();

        expect($query->pagination->page)->toBe(1)
            ->and($query->pagination->perPage)->toBe(Pagination::DEFAULT_PER_PAGE);
    });

    it('trims the search term it passes down', function () {
        expect(ListServicesInput::fromRequest(['search' => '  corte  '])->toQuery()->search)->toBe('corte');
    });

    it('carries no search term when the one it was given is blank', function (?string $search) {
        expect(ListServicesInput::fromRequest(['search' => $search])->toQuery()->search)->toBeNull();
    })->with(['missing' => null, 'empty' => '', 'spaces' => '   ', 'tab' => "\t"]);
});
