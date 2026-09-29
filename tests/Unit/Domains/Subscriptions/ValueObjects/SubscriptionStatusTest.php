<?php

declare(strict_types=1);

use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;

it('mirrors exactly the statuses Stripe reports, under Stripe\'s own names', function () {
    expect(array_map(fn (SubscriptionStatus $status) => $status->value, SubscriptionStatus::cases()))
        ->toEqualCanonicalizing([
            'incomplete',
            'trialing',
            'active',
            'past_due',
            'canceled',
            'unpaid',
            'incomplete_expired',
            'paused',
        ]);
});

it('classifies every status as paid up, entitling and terminal', function (
    SubscriptionStatus $status,
    bool $paidUp,
    bool $entitles,
    bool $terminal,
) {
    expect($status->isPaidUp())->toBe($paidUp)
        ->and($status->entitles())->toBe($entitles)
        ->and($status->isTerminal())->toBe($terminal);
})->with([
    'incomplete' => [SubscriptionStatus::Incomplete, false, false, false],
    'trialing' => [SubscriptionStatus::Trialing, true, true, false],
    'active' => [SubscriptionStatus::Active, true, true, false],
    'past due' => [SubscriptionStatus::PastDue, false, true, false],
    'canceled' => [SubscriptionStatus::Canceled, false, false, true],
    'unpaid' => [SubscriptionStatus::Unpaid, false, false, false],
    'incomplete expired' => [SubscriptionStatus::IncompleteExpired, false, false, true],
    'paused' => [SubscriptionStatus::Paused, false, false, false],
]);
