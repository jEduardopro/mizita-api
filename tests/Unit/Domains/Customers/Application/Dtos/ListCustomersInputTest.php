<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\ListCustomersInput;
use App\Domains\Customers\Exceptions\InvalidCustomerSearch;
use App\Domains\Customers\ValueObjects\CustomerSort;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SearchTerm;
use App\Shared\ValueObjects\SortDirection;

describe('reading a query string', function () {
    it('assembles itself from a well formed query string', function () {
        $input = ListCustomersInput::fromRequest([
            'search' => 'Ada',
            'sort' => 'created_at',
            'direction' => 'desc',
            'page' => 3,
            'per_page' => 25,
        ]);

        expect($input->search)->toBe('Ada')
            ->and($input->sort)->toBe('created_at')
            ->and($input->direction)->toBe('desc')
            ->and($input->page)->toBe(3)
            ->and($input->perPage)->toBe(25);
    });

    it('survives a query string with every key missing', function () {
        $input = ListCustomersInput::fromRequest([]);

        expect($input->search)->toBeNull()
            ->and($input->sort)->toBeNull()
            ->and($input->direction)->toBeNull()
            ->and($input->page)->toBeNull()
            ->and($input->perPage)->toBeNull();
    });

    it('reads the numbers a query string carries as strings', function () {
        $input = ListCustomersInput::fromRequest(['page' => '2', 'per_page' => '50']);

        expect($input->page)->toBe(2)
            ->and($input->perPage)->toBe(50);
    });

    it('reads a wrongly typed value as none', function (array $payload, string $field) {
        expect(ListCustomersInput::fromRequest($payload)->{$field})->toBeNull();
    })->with([
        'search as an array' => [['search' => ['Ada']], 'search'],
        'sort as an array' => [['sort' => ['name']], 'sort'],
        'direction as a boolean' => [['direction' => true], 'direction'],
        'page as a word' => [['page' => 'first'], 'page'],
        'page as an array' => [['page' => [2]], 'page'],
        'per page as a word' => [['per_page' => 'all'], 'perPage'],
    ]);

    it('holds a blank search as it arrived, leaving the search term to read it as nothing', function () {
        expect(ListCustomersInput::fromRequest(['search' => '   '])->search)->toBe('   ');
    });
});

describe('validating', function () {
    it('accepts a query string every rule agrees with', function () {
        expect(fn () => ListCustomersInput::fromRequest(['search' => 'Ada'])->validate())
            ->not->toThrow(Throwable::class);
    });

    it('accepts an empty query string, because a list needs no arguments', function () {
        expect(fn () => ListCustomersInput::fromRequest([])->validate())->not->toThrow(Throwable::class);
    });

    it('accepts a search term as long as it serves', function () {
        expect(fn () => ListCustomersInput::fromRequest([
            'search' => str_repeat('a', ListCustomersInput::MAXIMUM_SEARCH_LENGTH),
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('refuses a search term one character longer than it serves', function () {
        expect(fn () => ListCustomersInput::fromRequest([
            'search' => str_repeat('a', ListCustomersInput::MAXIMUM_SEARCH_LENGTH + 1),
        ])->validate())->toThrow(InvalidCustomerSearch::class);
    });

    it('measures a search term after trimming it', function () {
        expect(fn () => ListCustomersInput::fromRequest([
            'search' => '   '.str_repeat('a', ListCustomersInput::MAXIMUM_SEARCH_LENGTH).'   ',
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('counts the characters of a search term rather than its bytes', function () {
        expect(fn () => ListCustomersInput::fromRequest([
            'search' => str_repeat('á', ListCustomersInput::MAXIMUM_SEARCH_LENGTH),
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('never refuses a navigation value, however absurd', function () {
        expect(fn () => ListCustomersInput::fromRequest([
            'page' => -9999,
            'per_page' => 9999,
            'sort' => 'business_id',
            'direction' => 'sideways',
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('refuses with a failure the responder can classify', function () {
        $refusal = null;

        try {
            ListCustomersInput::fromRequest(['search' => str_repeat('a', 121)])->validate();
        } catch (InvalidCustomerSearch $caught) {
            $refusal = $caught;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe('invalid_customer_search')
            ->and($refusal?->kind())->toBe(DomainFailureKind::Invalid);
    });
});

describe('turning itself into a query', function () {
    it('carries the search, the sort, the direction and the pagination it was given', function () {
        $query = ListCustomersInput::fromRequest([
            'search' => 'Ada',
            'sort' => 'created_at',
            'direction' => 'desc',
            'page' => 2,
            'per_page' => 25,
        ])->toQuery();

        expect($query->search)->toBeInstanceOf(SearchTerm::class)
            ->and($query->search->tokens())->toBe(['ada'])
            ->and($query->search->raw())->toBe('Ada')
            ->and($query->sort)->toBe(CustomerSort::CreatedAt)
            ->and($query->direction)->toBe(SortDirection::Descending)
            ->and($query->pagination->page)->toBe(2)
            ->and($query->pagination->perPage)->toBe(25);
    });

    it('falls back to sorting by name instead of refusing an unknown sort', function (?string $sort) {
        expect(ListCustomersInput::fromRequest(['sort' => $sort])->toQuery()->sort)->toBe(CustomerSort::Name);
    })->with([
        'missing' => null,
        'unknown' => 'whatever',
        'a column it does not expose' => 'business_id',
        'uppercase' => 'NAME',
        'sql' => 'name; drop table customers',
        'empty' => '',
    ]);

    it('falls back to ascending instead of refusing an unknown direction', function (?string $direction) {
        expect(ListCustomersInput::fromRequest(['direction' => $direction])->toQuery()->direction)
            ->toBe(SortDirection::Ascending);
    })->with(['missing' => null, 'unknown' => 'sideways', 'uppercase' => 'DESC', 'empty' => '']);

    it('clamps the pagination instead of refusing it', function () {
        $query = ListCustomersInput::fromRequest(['page' => 0, 'per_page' => 9999])->toQuery();

        expect($query->pagination->page)->toBe(1)
            ->and($query->pagination->perPage)->toBe(Pagination::MAXIMUM_PER_PAGE);
    });

    it('paginates by default when nothing was asked for', function () {
        $query = ListCustomersInput::fromRequest([])->toQuery();

        expect($query->pagination->page)->toBe(1)
            ->and($query->pagination->perPage)->toBe(Pagination::DEFAULT_PER_PAGE);
    });

    it('carries no search term when the one it was given is blank', function (?string $search) {
        expect(ListCustomersInput::fromRequest(['search' => $search])->toQuery()->search)->toBeNull();
    })->with(['missing' => null, 'empty' => '', 'spaces' => '   ', 'tab' => "\t"]);

    it('splits the search term into the words it will look for', function () {
        expect(ListCustomersInput::fromRequest(['search' => 'Ada Lovelace'])->toQuery()->search->tokens())
            ->toBe(['ada', 'lovelace']);
    });

    it('hands the words down already folded, so an accented name is found without one', function () {
        $search = ListCustomersInput::fromRequest(['search' => 'José Ñuño'])->toQuery()->search;

        expect($search->tokens())->toBe(['jose', 'nuno'])
            ->and($search->raw())->toBe('José Ñuño');
    });

    it('trims the search term it passes down', function () {
        expect(ListCustomersInput::fromRequest(['search' => '  Ada  '])->toQuery()->search->raw())->toBe('Ada');
    });
});
