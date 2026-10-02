<?php

declare(strict_types=1);

use App\Domains\Platform\Application\Dtos\ListPlatformBusinessesInput;
use App\Domains\Platform\Exceptions\InvalidPlatformBusinessFilter;
use App\Domains\Platform\ValueObjects\PlatformBusinessSort;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SortDirection;

describe('reading a query string', function () {
    it('assembles itself from a well formed query string', function () {
        $input = ListPlatformBusinessesInput::fromRequest([
            'search' => 'ada',
            'sort' => 'name',
            'direction' => 'asc',
            'page' => 2,
            'per_page' => 50,
        ]);

        expect($input->search)->toBe('ada')
            ->and($input->sort)->toBe('name')
            ->and($input->direction)->toBe('asc')
            ->and($input->page)->toBe(2)
            ->and($input->perPage)->toBe(50);
    });

    it('survives a query string with every key missing', function () {
        $input = ListPlatformBusinessesInput::fromRequest([]);

        expect($input->search)->toBeNull()
            ->and($input->sort)->toBeNull()
            ->and($input->direction)->toBeNull()
            ->and($input->page)->toBeNull()
            ->and($input->perPage)->toBeNull();
    });

    it('reads the page and its size from the strings a query string carries', function () {
        $input = ListPlatformBusinessesInput::fromRequest(['page' => '4', 'per_page' => '25']);

        expect($input->page)->toBe(4)
            ->and($input->perPage)->toBe(25);
    });

    it('reads a wrongly typed value as none', function (array $payload, string $field) {
        expect(ListPlatformBusinessesInput::fromRequest($payload)->{$field})->toBeNull();
    })->with([
        'search as an array' => [['search' => ['ada']], 'search'],
        'search as a number' => [['search' => 2749], 'search'],
        'sort as an array' => [['sort' => ['name']], 'sort'],
        'sort as a boolean' => [['sort' => true], 'sort'],
        'direction as an array' => [['direction' => ['asc']], 'direction'],
        'page as words' => [['page' => 'two'], 'page'],
        'page as an array' => [['page' => [2]], 'page'],
        'page size as words' => [['per_page' => 'all'], 'perPage'],
        'page size as null' => [['per_page' => null], 'perPage'],
    ]);
});

describe('validating', function () {
    it('accepts an empty query string', function () {
        expect(fn () => ListPlatformBusinessesInput::fromRequest([])->validate())->not->toThrow(Throwable::class);
    });

    it('accepts a query string every rule agrees with', function (string $sort, string $direction) {
        expect(fn () => ListPlatformBusinessesInput::fromRequest([
            'search' => 'Barbería',
            'sort' => $sort,
            'direction' => $direction,
            'page' => 1,
            'per_page' => 20,
        ])->validate())->not->toThrow(Throwable::class);
    })->with([
        'created_at',
        'name',
        'services_count',
        'customers_count',
    ])->with(['asc', 'desc']);

    it('accepts a search at the edge of what it may hold', function (string $search) {
        expect(fn () => ListPlatformBusinessesInput::fromRequest(['search' => $search])->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'exactly 120 characters' => str_repeat('a', ListPlatformBusinessesInput::MAXIMUM_SEARCH_LENGTH),
        '120 accented characters' => str_repeat('ñ', ListPlatformBusinessesInput::MAXIMUM_SEARCH_LENGTH),
        '120 characters padded with whitespace' => '   '.str_repeat('a', ListPlatformBusinessesInput::MAXIMUM_SEARCH_LENGTH).'   ',
        'empty' => '',
        'blank' => "  \t ",
    ]);

    it('accepts a page and a page size at the edges of their range', function (int $page, int $perPage) {
        expect(fn () => ListPlatformBusinessesInput::fromRequest(['page' => $page, 'per_page' => $perPage])->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'the first page, one row' => [1, 1],
        'a far page, the largest size' => [9999, Pagination::MAXIMUM_PER_PAGE],
    ]);

    it('refuses a filter it cannot serve', function (array $payload, string $reason) {
        expect(fn () => ListPlatformBusinessesInput::fromRequest($payload)->validate())
            ->toThrow(InvalidPlatformBusinessFilter::class, $reason);
    })->with([
        'a search of 121 characters' => [['search' => str_repeat('a', 121)], 'may not run past 120 characters'],
        'a search of 121 accented characters' => [['search' => str_repeat('é', 121)], 'may not run past 120 characters'],
        'a sort by a column it does not serve' => [['sort' => 'plan'], 'cannot be sorted by [plan]'],
        'an uppercase sort' => [['sort' => 'NAME'], 'cannot be sorted by [NAME]'],
        'an empty sort' => [['sort' => ''], 'cannot be sorted by []'],
        'an unknown direction' => [['direction' => 'sideways'], 'The sort direction [sideways]'],
        'an uppercase direction' => [['direction' => 'DESC'], 'The sort direction [DESC]'],
        'an empty direction' => [['direction' => ''], 'The sort direction []'],
        'page zero' => [['page' => 0], 'The page [0]'],
        'a negative page' => [['page' => -1], 'The page [-1]'],
        'a page size of zero' => [['per_page' => 0], 'got [0]'],
        'a negative page size' => [['per_page' => -5], 'got [-5]'],
        'a page size one past the largest' => [['per_page' => 101], 'got [101]'],
    ]);

    it('refuses with a failure the edge knows how to render', function () {
        try {
            ListPlatformBusinessesInput::fromRequest(['sort' => 'plan'])->validate();
        } catch (InvalidPlatformBusinessFilter $failure) {
            expect($failure)->toBeInstanceOf(DomainFailure::class);

            return;
        }

        $this->fail('The unknown sort was accepted.');
    });

    it('validates a DTO a caller built directly, without a query string', function () {
        expect(fn () => (new ListPlatformBusinessesInput(null, null, null, 0, null))->validate())
            ->toThrow(InvalidPlatformBusinessFilter::class, 'The page [0]');
    });
});

describe('turning itself into a query', function () {
    it('carries the search, the sort, the direction and the pagination it was given', function () {
        $query = ListPlatformBusinessesInput::fromRequest([
            'search' => '  ada   lovelace ',
            'sort' => 'services_count',
            'direction' => 'asc',
            'page' => 2,
            'per_page' => 50,
        ])->toQuery();

        expect($query->search?->raw())->toBe('ada lovelace')
            ->and($query->search?->tokens())->toBe(['ada', 'lovelace'])
            ->and($query->sort)->toBe(PlatformBusinessSort::ServicesCount)
            ->and($query->direction)->toBe(SortDirection::Ascending)
            ->and($query->pagination->page)->toBe(2)
            ->and($query->pagination->perPage)->toBe(50);
    });

    it('lists every business newest first, on the first page of the default size, by default', function () {
        $query = ListPlatformBusinessesInput::fromRequest([])->toQuery();

        expect($query->search)->toBeNull()
            ->and($query->sort)->toBe(PlatformBusinessSort::CreatedAt)
            ->and($query->direction)->toBe(SortDirection::Descending)
            ->and($query->pagination->page)->toBe(1)
            ->and($query->pagination->perPage)->toBe(Pagination::DEFAULT_PER_PAGE);
    });

    it('sorts by each column it serves', function (string $sort, PlatformBusinessSort $expected) {
        expect(ListPlatformBusinessesInput::fromRequest(['sort' => $sort])->toQuery()->sort)->toBe($expected);
    })->with([
        'created_at' => ['created_at', PlatformBusinessSort::CreatedAt],
        'name' => ['name', PlatformBusinessSort::Name],
        'services_count' => ['services_count', PlatformBusinessSort::ServicesCount],
        'customers_count' => ['customers_count', PlatformBusinessSort::CustomersCount],
    ]);

    it('looks for nothing when the search it was given is blank', function (?string $search) {
        expect(ListPlatformBusinessesInput::fromRequest(['search' => $search])->toQuery()->search)->toBeNull();
    })->with(['missing' => null, 'empty' => '', 'spaces' => '   ', 'tab' => "\t"]);

    it('folds accents out of the words it searches for', function () {
        expect(ListPlatformBusinessesInput::fromRequest(['search' => 'Estética Ñandú'])->toQuery()->search?->tokens())
            ->toBe(['estetica', 'nandu']);
    });
});
