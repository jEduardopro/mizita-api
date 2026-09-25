<?php

declare(strict_types=1);

use App\Domains\Accounts\ValueObjects\DeletionGracePeriod;
use App\Domains\Businesses\ValueObjects\ClosureRetention;

it('keeps the account deletion grace period equal to the closed business retention period', function () {
    expect(DeletionGracePeriod::DAYS)->toBe(ClosureRetention::DAYS);
});

it('schedules the account grace end and the business purge on the same instant for the same start', function () {
    $closedAt = new DateTimeImmutable('2026-03-29T01:30:00+00:00');

    expect(DeletionGracePeriod::endingFrom($closedAt))->toEqual(ClosureRetention::purgeDueAt($closedAt));
});
