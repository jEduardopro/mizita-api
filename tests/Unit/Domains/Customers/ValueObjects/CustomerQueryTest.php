<?php

declare(strict_types=1);

use App\Domains\Customers\ValueObjects\CustomerQuery;
use App\Domains\Customers\ValueObjects\CustomerSort;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SearchTerm;
use App\Shared\ValueObjects\SortDirection;

it('carries the search, the sort, the direction and the page the repository will read', function () {
    $query = new CustomerQuery(
        search: SearchTerm::of('Ada'),
        sort: CustomerSort::CreatedAt,
        direction: SortDirection::Descending,
        pagination: Pagination::of(2, 25),
    );

    expect($query->search?->tokens())->toBe(['ada'])
        ->and($query->search?->raw())->toBe('Ada')
        ->and($query->sort)->toBe(CustomerSort::CreatedAt)
        ->and($query->direction)->toBe(SortDirection::Descending)
        ->and($query->pagination->page)->toBe(2)
        ->and($query->pagination->perPage)->toBe(25);
});

it('reads a listing with no search term as one asking for every customer', function () {
    $query = new CustomerQuery(
        search: SearchTerm::of(null),
        sort: CustomerSort::Name,
        direction: SortDirection::Ascending,
        pagination: Pagination::of(null, null),
    );

    expect($query->search)->toBeNull()
        ->and($query->pagination->page)->toBe(1)
        ->and($query->pagination->perPage)->toBe(Pagination::DEFAULT_PER_PAGE);
});

it('names the sort and the direction with enums, so no caller string can reach the query', function () {
    $query = new CustomerQuery(
        search: null,
        sort: CustomerSort::Name,
        direction: SortDirection::Ascending,
        pagination: Pagination::of(1, 20),
    );

    expect($query->sort)->toBeInstanceOf(CustomerSort::class)
        ->and($query->direction)->toBeInstanceOf(SortDirection::class);
});
