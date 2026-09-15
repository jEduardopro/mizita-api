<?php

declare(strict_types=1);

use App\Shared\ValueObjects\Pagination;

it('defaults to the first page and the default page size when nothing is asked for', function () {
    $pagination = Pagination::of(null, null);

    expect($pagination->page)->toBe(1)
        ->and($pagination->perPage)->toBe(Pagination::DEFAULT_PER_PAGE)
        ->and($pagination->offset())->toBe(0);
});

it('keeps a page and a page size it can honour', function () {
    $pagination = Pagination::of(3, 25);

    expect($pagination->page)->toBe(3)
        ->and($pagination->perPage)->toBe(25);
});

it('clamps a page that cannot exist to the first one', function (?int $page, int $expected) {
    expect(Pagination::of($page, 20)->page)->toBe($expected);
})->with([
    'zero' => [0, 1],
    'negative' => [-1, 1],
    'far negative' => [-9999, 1],
    'null' => [null, 1],
    'first' => [1, 1],
]);

it('clamps a page size into the range it serves', function (?int $perPage, int $expected) {
    expect(Pagination::of(1, $perPage)->perPage)->toBe($expected);
})->with([
    'zero' => [0, 1],
    'negative' => [-5, 1],
    'one' => [1, 1],
    'maximum' => [Pagination::MAXIMUM_PER_PAGE, Pagination::MAXIMUM_PER_PAGE],
    'beyond the maximum' => [9999, Pagination::MAXIMUM_PER_PAGE],
    'null' => [null, Pagination::DEFAULT_PER_PAGE],
]);

it('never refuses a navigation value, however absurd', function () {
    expect(fn () => Pagination::of(-9999, -9999))->not->toThrow(Throwable::class);
});

it('offsets by the pages already passed', function (int $page, int $perPage, int $offset) {
    expect(Pagination::of($page, $perPage)->offset())->toBe($offset);
})->with([
    'first page' => [1, 20, 0],
    'second page' => [2, 20, 20],
    'tenth page of twenty five' => [10, 25, 225],
    'clamped page' => [0, 20, 0],
    'clamped page size' => [3, 9999, 200],
]);
