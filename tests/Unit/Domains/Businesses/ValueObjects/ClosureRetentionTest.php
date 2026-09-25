<?php

declare(strict_types=1);

use App\Domains\Businesses\ValueObjects\ClosureRetention;

it('retains a closed business for thirty days', function () {
    expect(ClosureRetention::DAYS)->toBe(30);
});

it('makes a business due for purge thirty days after it closed', function () {
    expect(ClosureRetention::purgeDueAt(new DateTimeImmutable('2026-02-01T10:00:00+00:00')))
        ->toEqual(new DateTimeImmutable('2026-03-03T10:00:00+00:00'));
});

it('treats as due every business closed no later than thirty days before now', function () {
    expect(ClosureRetention::closedNoLaterThanFor(new DateTimeImmutable('2026-03-03T10:00:00+00:00')))
        ->toEqual(new DateTimeImmutable('2026-02-01T10:00:00+00:00'));
});

it('places the cutoff so a business closed exactly at it is due right now', function () {
    $now = new DateTimeImmutable('2026-09-25T03:00:00+00:00');

    expect(ClosureRetention::purgeDueAt(ClosureRetention::closedNoLaterThanFor($now)))->toEqual($now);
});

it('counts elapsed UTC days across both daylight saving changes', function (string $closedAt) {
    $closed = new DateTimeImmutable($closedAt);

    expect(ClosureRetention::purgeDueAt($closed)->getTimestamp() - $closed->getTimestamp())
        ->toBe(ClosureRetention::DAYS * 24 * 60 * 60);
})->with([
    'spanning the spring-forward Sunday in Madrid' => '2026-03-15T12:00:00+00:00',
    'spanning the fall-back Sunday in Madrid' => '2026-10-10T00:30:00+00:00',
]);

it('keeps the leap day inside the retention', function () {
    expect(ClosureRetention::purgeDueAt(new DateTimeImmutable('2028-02-15T10:00:00+00:00')))
        ->toEqual(new DateTimeImmutable('2028-03-16T10:00:00+00:00'));
});
