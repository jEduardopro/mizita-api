<?php

declare(strict_types=1);

use App\Shared\ValueObjects\Paginated;
use App\Shared\ValueObjects\Pagination;

it('carries the items, the total and the pagination it was given', function () {
    $page = Paginated::of(['a', 'b'], 7, Pagination::of(2, 2));

    expect($page->items)->toBe(['a', 'b'])
        ->and($page->total)->toBe(7)
        ->and($page->pagination->page)->toBe(2)
        ->and($page->pagination->perPage)->toBe(2);
});

it('refuses to report a negative total', function () {
    expect(Paginated::of([], -3, Pagination::of(1, 20))->total)->toBe(0);
});

it('counts the last page from the total and the page size', function (int $total, int $perPage, int $lastPage) {
    expect(Paginated::of([], $total, Pagination::of(1, $perPage))->lastPage())->toBe($lastPage);
})->with([
    'nothing at all' => [0, 20, 1],
    'a single row' => [1, 20, 1],
    'a full first page' => [20, 20, 1],
    'one row into the second page' => [21, 20, 2],
    'an exact multiple' => [100, 25, 4],
    'one short of a multiple' => [99, 25, 4],
    'one past a multiple' => [101, 25, 5],
]);

it('maps its items and keeps the total, the page and the page size', function () {
    $page = Paginated::of([1, 2, 3], 42, Pagination::of(2, 3));

    $mapped = $page->map(static fn (int $item): string => "item-{$item}");

    expect($mapped->items)->toBe(['item-1', 'item-2', 'item-3'])
        ->and($mapped->total)->toBe(42)
        ->and($mapped->pagination->page)->toBe(2)
        ->and($mapped->pagination->perPage)->toBe(3)
        ->and($mapped->lastPage())->toBe($page->lastPage());
});

it('leaves the page it mapped from untouched', function () {
    $page = Paginated::of([1, 2], 2, Pagination::of(1, 20));

    $page->map(static fn (int $item): int => $item * 10);

    expect($page->items)->toBe([1, 2]);
});

it('maps an empty page to an empty page', function () {
    $mapped = Paginated::of([], 0, Pagination::of(9, 20))
        ->map(static fn (mixed $item): mixed => $item);

    expect($mapped->items)->toBe([])
        ->and($mapped->total)->toBe(0)
        ->and($mapped->pagination->page)->toBe(9);
});

it('hands back a list, never a map with holes', function () {
    $page = Paginated::of([1, 2, 3], 3, Pagination::of(1, 20));

    $mapped = $page->map(static fn (int $item): int => $item);

    expect(array_keys($mapped->items))->toBe([0, 1, 2]);
});
