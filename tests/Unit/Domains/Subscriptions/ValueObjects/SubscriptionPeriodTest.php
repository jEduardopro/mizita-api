<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionPeriod;
use App\Domains\Subscriptions\Exceptions\SubscriptionNotExtendable;
use App\Domains\Subscriptions\ValueObjects\SubscriptionPeriod;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

function periodInstant(string $atom): DateTimeImmutable
{
    return new DateTimeImmutable($atom);
}

function junePeriod(): SubscriptionPeriod
{
    return SubscriptionPeriod::between(
        periodInstant('2026-06-01T00:00:00+00:00'),
        periodInstant('2026-07-01T00:00:00+00:00'),
    );
}

describe('building a period', function () {
    it('keeps both ends', function () {
        $period = junePeriod();

        expect($period->startsAt)->toEqual(periodInstant('2026-06-01T00:00:00+00:00'))
            ->and($period->endsAt)->toEqual(periodInstant('2026-07-01T00:00:00+00:00'));
    });

    it('accepts an open-ended period', function () {
        expect(SubscriptionPeriod::between(periodInstant('2026-06-01T00:00:00+00:00'), null)->endsAt)->toBeNull();
    });

    it('accepts an empty period that ends where it starts', function () {
        $instant = periodInstant('2026-06-01T00:00:00+00:00');

        expect(SubscriptionPeriod::between($instant, $instant)->endsAt)->toEqual($instant);
    });

    it('rejects a period that ends before it starts', function () {
        expect(fn () => SubscriptionPeriod::between(
            periodInstant('2026-06-01T00:00:00+00:00'),
            periodInstant('2026-05-31T23:59:59+00:00'),
        ))->toThrow(InvalidSubscriptionPeriod::class);
    });

    it('compares the ends as instants, not as wall clock readings', function () {
        $period = SubscriptionPeriod::between(
            periodInstant('2026-06-01T10:00:00+02:00'),
            periodInstant('2026-06-01T09:00:00+00:00'),
        );

        expect($period->endsAt)->toEqual(periodInstant('2026-06-01T09:00:00+00:00'));
    });

    it('refuses an inverted period as an invalid domain failure', function () {
        try {
            SubscriptionPeriod::between(periodInstant('2026-06-02T00:00:00+00:00'), periodInstant('2026-06-01T00:00:00+00:00'));
        } catch (InvalidSubscriptionPeriod $failure) {
            expect($failure)->toBeInstanceOf(DomainFailure::class)
                ->and($failure->errorCode())->toBe('invalid_subscription_period')
                ->and($failure->kind())->toBe(DomainFailureKind::Invalid);

            return;
        }

        $this->fail('An inverted period was accepted.');
    });

    it('restores an inverted period without holding it to the creation rule', function () {
        $period = SubscriptionPeriod::restore(
            periodInstant('2026-06-02T00:00:00+00:00'),
            periodInstant('2026-06-01T00:00:00+00:00'),
        );

        expect($period->endsAt)->toEqual(periodInstant('2026-06-01T00:00:00+00:00'));
    });
});

describe('containment is half open', function () {
    it('tells whether an instant falls inside the period', function (string $instant, bool $contained) {
        expect(junePeriod()->contains(periodInstant($instant)))->toBe($contained);
    })->with([
        'the first instant' => ['2026-06-01T00:00:00+00:00', true],
        'the middle' => ['2026-06-15T12:00:00+00:00', true],
        'the last second' => ['2026-06-30T23:59:59+00:00', true],
        'the end itself' => ['2026-07-01T00:00:00+00:00', false],
        'after the end' => ['2026-07-02T00:00:00+00:00', false],
        'one second before the start' => ['2026-05-31T23:59:59+00:00', false],
    ]);

    it('contains every instant from the start onward when open ended', function (string $instant, bool $contained) {
        $period = SubscriptionPeriod::between(periodInstant('2026-06-01T00:00:00+00:00'), null);

        expect($period->contains(periodInstant($instant)))->toBe($contained);
    })->with([
        'the start' => ['2026-06-01T00:00:00+00:00', true],
        'decades later' => ['2099-12-31T23:59:59+00:00', true],
        'before the start' => ['2026-05-31T23:59:59+00:00', false],
    ]);

    it('contains nothing when it ends where it starts', function () {
        $instant = periodInstant('2026-06-01T00:00:00+00:00');

        expect(SubscriptionPeriod::between($instant, $instant)->contains($instant))->toBeFalse();
    });

    it('compares an instant in another zone by the moment it names', function () {
        expect(junePeriod()->contains(periodInstant('2026-07-01T01:59:59+02:00')))->toBeTrue()
            ->and(junePeriod()->contains(periodInstant('2026-07-01T02:00:00+02:00')))->toBeFalse();
    });
});

describe('having ended', function () {
    it('tells whether the period has ended by an instant', function (string $now, bool $ended) {
        expect(junePeriod()->hasEndedBy(periodInstant($now)))->toBe($ended);
    })->with([
        'before the end' => ['2026-06-30T23:59:59+00:00', false],
        'at the end' => ['2026-07-01T00:00:00+00:00', true],
        'after the end' => ['2026-08-01T00:00:00+00:00', true],
    ]);

    it('never ends when it is open ended', function () {
        expect(SubscriptionPeriod::between(periodInstant('2026-06-01T00:00:00+00:00'), null)
            ->hasEndedBy(periodInstant('2099-01-01T00:00:00+00:00')))->toBeFalse();
    });
});

describe('extending', function () {
    it('moves the end later and keeps the start', function () {
        $extended = junePeriod()->extendedUntil(periodInstant('2026-08-01T00:00:00+00:00'));

        expect($extended->startsAt)->toEqual(periodInstant('2026-06-01T00:00:00+00:00'))
            ->and($extended->endsAt)->toEqual(periodInstant('2026-08-01T00:00:00+00:00'));
    });

    it('leaves the original period untouched', function () {
        $period = junePeriod();

        $period->extendedUntil(periodInstant('2026-08-01T00:00:00+00:00'));

        expect($period->endsAt)->toEqual(periodInstant('2026-07-01T00:00:00+00:00'));
    });

    it('accepts an end one second later than the current one', function () {
        expect(junePeriod()->extendedUntil(periodInstant('2026-07-01T00:00:01+00:00'))->endsAt)
            ->toEqual(periodInstant('2026-07-01T00:00:01+00:00'));
    });

    it('refuses an end that is not later than the current one', function (string $endsAt) {
        expect(fn () => junePeriod()->extendedUntil(periodInstant($endsAt)))
            ->toThrow(SubscriptionNotExtendable::class);
    })->with([
        'the same end' => '2026-07-01T00:00:00+00:00',
        'the same end in another zone' => '2026-07-01T02:00:00+02:00',
        'an earlier end' => '2026-06-20T00:00:00+00:00',
        'before the start' => '2026-05-01T00:00:00+00:00',
    ]);

    it('refuses to extend an open-ended period', function () {
        expect(fn () => SubscriptionPeriod::between(periodInstant('2026-06-01T00:00:00+00:00'), null)
            ->extendedUntil(periodInstant('2027-01-01T00:00:00+00:00')))
            ->toThrow(SubscriptionNotExtendable::class);
    });

    it('refuses an extension as an invalid domain failure', function () {
        try {
            junePeriod()->extendedUntil(periodInstant('2026-06-01T00:00:00+00:00'));
        } catch (SubscriptionNotExtendable $failure) {
            expect($failure)->toBeInstanceOf(DomainFailure::class)
                ->and($failure->errorCode())->toBe('subscription_not_extendable')
                ->and($failure->kind())->toBe(DomainFailureKind::Invalid);

            return;
        }

        $this->fail('A shortening was accepted as an extension.');
    });
});

describe('truncating', function () {
    it('ends the period at the given instant when it falls inside', function () {
        $truncated = junePeriod()->truncatedAt(periodInstant('2026-06-15T12:00:00+00:00'));

        expect($truncated->startsAt)->toEqual(periodInstant('2026-06-01T00:00:00+00:00'))
            ->and($truncated->endsAt)->toEqual(periodInstant('2026-06-15T12:00:00+00:00'));
    });

    it('ends a period that has not started yet at its own start, never before it', function () {
        $truncated = junePeriod()->truncatedAt(periodInstant('2026-05-20T00:00:00+00:00'));

        expect($truncated->startsAt)->toEqual(periodInstant('2026-06-01T00:00:00+00:00'))
            ->and($truncated->endsAt)->toEqual(periodInstant('2026-06-01T00:00:00+00:00'));
    });

    it('never inverts, wherever the cut falls', function (string $now) {
        $truncated = junePeriod()->truncatedAt(periodInstant($now));

        expect($truncated->endsAt >= $truncated->startsAt)->toBeTrue();
    })->with([
        'long before the start' => '2020-01-01T00:00:00+00:00',
        'at the start' => '2026-06-01T00:00:00+00:00',
        'inside' => '2026-06-10T00:00:00+00:00',
        'at the end' => '2026-07-01T00:00:00+00:00',
        'long after the end' => '2030-01-01T00:00:00+00:00',
    ]);

    it('keeps an end that has already passed instead of moving it later', function () {
        $truncated = junePeriod()->truncatedAt(periodInstant('2026-08-01T00:00:00+00:00'));

        expect($truncated->endsAt)->toEqual(periodInstant('2026-07-01T00:00:00+00:00'));
    });

    it('closes an open-ended period at the given instant', function () {
        $truncated = SubscriptionPeriod::between(periodInstant('2026-06-01T00:00:00+00:00'), null)
            ->truncatedAt(periodInstant('2026-09-01T00:00:00+00:00'));

        expect($truncated->endsAt)->toEqual(periodInstant('2026-09-01T00:00:00+00:00'));
    });

    it('no longer contains the cut instant', function () {
        $now = periodInstant('2026-06-15T12:00:00+00:00');

        expect(junePeriod()->truncatedAt($now)->contains($now))->toBeFalse();
    });
});
