<?php

declare(strict_types=1);

use App\Shared\ValueObjects\SortDirection;

it('offers exactly the two directions a query may take', function () {
    expect(array_map(
        static fn (SortDirection $direction): string => $direction->value,
        SortDirection::cases(),
    ))->toBe(['asc', 'desc']);
});

it('refuses a direction it does not serve', function (string $value) {
    expect(SortDirection::tryFrom($value))->toBeNull();
})->with([
    'uppercase' => 'ASC',
    'sql injection' => 'asc; drop table services',
    'word' => 'ascending',
    'empty' => '',
]);
