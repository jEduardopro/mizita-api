<?php

declare(strict_types=1);

use App\Domains\Accounts\ValueObjects\DeletionGracePeriod;

it('lasts thirty days', function () {
    expect(DeletionGracePeriod::DAYS)->toBe(30);
});

it('ends thirty days after the instant it starts from', function (string $start, string $end) {
    expect(DeletionGracePeriod::endingFrom(new DateTimeImmutable($start))->format(DATE_ATOM))->toBe($end);
})->with([
    'within a month' => ['2026-01-01T12:00:00+00:00', '2026-01-31T12:00:00+00:00'],
    'across a month end' => ['2026-01-15T08:00:00+00:00', '2026-02-14T08:00:00+00:00'],
    'across a year end' => ['2025-12-20T09:15:00+00:00', '2026-01-19T09:15:00+00:00'],
    'across a leap day' => ['2028-02-15T00:00:00+00:00', '2028-03-16T00:00:00+00:00'],
    'across a common february' => ['2027-02-15T00:00:00+00:00', '2027-03-17T00:00:00+00:00'],
    'one second before midnight' => ['2026-06-30T23:59:59+00:00', '2026-07-30T23:59:59+00:00'],
]);

it('spans exactly thirty days of elapsed time across both Madrid DST switches, because it counts in UTC', function (string $start) {
    $startsAt = new DateTimeImmutable($start);

    expect(DeletionGracePeriod::endingFrom($startsAt)->getTimestamp() - $startsAt->getTimestamp())
        ->toBe(DeletionGracePeriod::DAYS * 86400);
})->with([
    'requested the night Madrid springs forward' => '2026-03-29T00:30:00+00:00',
    'requested the night Madrid falls back' => '2026-10-25T00:30:00+00:00',
    'requested a week before spring forward' => '2026-03-22T12:00:00+00:00',
    'requested a week before fall back' => '2026-10-18T12:00:00+00:00',
]);

it('leaves the starting instant untouched', function () {
    $startsAt = new DateTimeImmutable('2026-01-01T12:00:00+00:00');

    DeletionGracePeriod::endingFrom($startsAt);

    expect($startsAt->format(DATE_ATOM))->toBe('2026-01-01T12:00:00+00:00');
});
