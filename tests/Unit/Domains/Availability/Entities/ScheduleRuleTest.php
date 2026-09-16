<?php

declare(strict_types=1);

use App\Domains\Availability\Entities\ScheduleRule;
use App\Domains\Availability\Exceptions\ScheduleIntervalInverted;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\TimeOfDay;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Availability\ScheduleFixtures;
use Tests\Support\FakeBusinessContext;

function restoredScheduleRule(
    string $startsAt = '09:00',
    string $endsAt = '14:00',
    Weekday $weekday = Weekday::Monday,
    ScheduleOwnerType $ownerType = ScheduleOwnerType::Business,
    ?string $ownerId = null,
): ScheduleRule {
    return ScheduleRule::restore(
        id: ScheduleFixtures::RULE_ID,
        businessId: FakeBusinessContext::BUSINESS_ID,
        ownerType: $ownerType,
        ownerId: $ownerId ?? FakeBusinessContext::BUSINESS_ID,
        weekday: $weekday,
        startsAt: TimeOfDay::fromString($startsAt),
        endsAt: TimeOfDay::fromString($endsAt),
        createdAt: ScheduleFixtures::now(),
    );
}

describe('opening a recurring interval', function () {
    it('holds everything it was given, scoped to the business that asked', function () {
        $rule = ScheduleFixtures::rule(weekday: Weekday::Wednesday);

        expect($rule->id)->toBe(ScheduleFixtures::RULE_ID)
            ->and($rule->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($rule->ownerType)->toBe(ScheduleOwnerType::Business)
            ->and($rule->ownerId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($rule->weekday)->toBe(Weekday::Wednesday)
            ->and($rule->startsAt()->toString())->toBe('09:00')
            ->and($rule->endsAt()->toString())->toBe('14:00')
            ->and($rule->createdAt)->toEqual(ScheduleFixtures::now());
    });

    it('belongs to a staff member just as readily as to a business', function () {
        $rule = ScheduleFixtures::rule(
            ownerType: ScheduleOwnerType::StaffMember,
            ownerId: ScheduleFixtures::STAFF_ID,
        );

        expect($rule->ownerType)->toBe(ScheduleOwnerType::StaffMember)
            ->and($rule->ownerId)->toBe(ScheduleFixtures::STAFF_ID)
            ->and($rule->businessId)->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('carries the uuid it was handed as its identity, never a row number', function () {
        expect(ScheduleFixtures::rule()->id)->toBe(ScheduleFixtures::RULE_ID)
            ->and(ScheduleFixtures::rule()->id)->toBeString();
    });

    it('refuses an interval that ends before it starts', function () {
        expect(fn () => ScheduleFixtures::rule(startsAt: '18:00', endsAt: '09:00'))
            ->toThrow(ScheduleIntervalInverted::class);
    });

    it('refuses an interval of no length at all', function () {
        expect(fn () => ScheduleFixtures::rule(startsAt: '14:00', endsAt: '14:00'))
            ->toThrow(ScheduleIntervalInverted::class);
    });

    it('accepts an interval a single minute long', function () {
        expect(ScheduleFixtures::rule(startsAt: '09:00', endsAt: '09:01')->endsAt()->toString())->toBe('09:01');
    });

    it('accepts a day that runs from midnight to the last minute', function () {
        $rule = ScheduleFixtures::rule(startsAt: '00:00', endsAt: '23:59');

        expect($rule->startsAt()->minutesFromMidnight())->toBe(0)
            ->and($rule->endsAt()->minutesFromMidnight())->toBe(1439);
    });

    it('says which two times it turned down', function () {
        expect(fn () => ScheduleFixtures::rule(startsAt: '18:00', endsAt: '09:00'))
            ->toThrow(
                ScheduleIntervalInverted::class,
                'A schedule interval must end after it starts, got [18:00] to [09:00].',
            );
    });

    it('classifies an inverted interval as something the caller has to correct', function () {
        $failure = ScheduleIntervalInverted::between('18:00', '09:00');

        expect($failure->errorCode())->toBe('schedule_interval_inverted')
            ->and($failure->kind())->toBe(DomainFailureKind::Invalid);
    });
});

describe('restoring an interval from a row', function () {
    it('skips the creation-time invariant, so a stored oddity still reads back', function () {
        $rule = restoredScheduleRule(startsAt: '18:00', endsAt: '09:00');

        expect($rule->startsAt()->toString())->toBe('18:00')
            ->and($rule->endsAt()->toString())->toBe('09:00');
    });

    it('keeps the instant the row was created rather than reaching for a clock', function () {
        expect(restoredScheduleRule()->createdAt)->toEqual(ScheduleFixtures::now());
    });
});

describe('moving an interval', function () {
    it('takes the two new times', function () {
        $rule = ScheduleFixtures::rule();

        $rule->reschedule(TimeOfDay::fromString('10:00'), TimeOfDay::fromString('16:30'));

        expect($rule->startsAt()->toString())->toBe('10:00')
            ->and($rule->endsAt()->toString())->toBe('16:30');
    });

    it('refuses to end before it starts', function () {
        expect(fn () => ScheduleFixtures::rule()->reschedule(
            TimeOfDay::fromString('18:00'),
            TimeOfDay::fromString('09:00'),
        ))->toThrow(ScheduleIntervalInverted::class);
    });

    it('leaves the interval as it was when it refuses', function () {
        $rule = ScheduleFixtures::rule();

        try {
            $rule->reschedule(TimeOfDay::fromString('18:00'), TimeOfDay::fromString('09:00'));
        } catch (ScheduleIntervalInverted) {
        }

        expect($rule->startsAt()->toString())->toBe('09:00')
            ->and($rule->endsAt()->toString())->toBe('14:00');
    });

    it('keeps the day, the owner and the identity it already had', function () {
        $rule = ScheduleFixtures::rule(weekday: Weekday::Friday);

        $rule->reschedule(TimeOfDay::fromString('10:00'), TimeOfDay::fromString('11:00'));

        expect($rule->weekday)->toBe(Weekday::Friday)
            ->and($rule->id)->toBe(ScheduleFixtures::RULE_ID)
            ->and($rule->ownerId)->toBe(FakeBusinessContext::BUSINESS_ID);
    });
});

describe('asking whether two intervals collide', function () {
    it('reads two intervals that touch at the boundary as not colliding', function () {
        $morning = restoredScheduleRule(startsAt: '09:00', endsAt: '14:00');
        $afternoon = restoredScheduleRule(startsAt: '14:00', endsAt: '18:00');

        expect($morning->overlaps($afternoon))->toBeFalse()
            ->and($afternoon->overlaps($morning))->toBeFalse();
    });

    it('reads two intervals that share a single minute as colliding', function () {
        $morning = restoredScheduleRule(startsAt: '09:00', endsAt: '14:01');
        $afternoon = restoredScheduleRule(startsAt: '14:00', endsAt: '18:00');

        expect($morning->overlaps($afternoon))->toBeTrue()
            ->and($afternoon->overlaps($morning))->toBeTrue();
    });

    it('reads an interval swallowed by another as colliding', function () {
        $whole = restoredScheduleRule(startsAt: '09:00', endsAt: '18:00');
        $inside = restoredScheduleRule(startsAt: '12:00', endsAt: '13:00');

        expect($whole->overlaps($inside))->toBeTrue()
            ->and($inside->overlaps($whole))->toBeTrue();
    });

    it('reads an interval against its own twin as colliding', function () {
        expect(restoredScheduleRule()->overlaps(restoredScheduleRule()))->toBeTrue();
    });

    it('never collides across two different days', function () {
        $monday = restoredScheduleRule(weekday: Weekday::Monday);
        $tuesday = restoredScheduleRule(weekday: Weekday::Tuesday);

        expect($monday->overlaps($tuesday))->toBeFalse()
            ->and($tuesday->overlaps($monday))->toBeFalse();
    });

    it('leaves the owner out of the question, which is what the weekly schedule groups by', function () {
        $business = restoredScheduleRule(ownerType: ScheduleOwnerType::Business);
        $staffMember = restoredScheduleRule(
            ownerType: ScheduleOwnerType::StaffMember,
            ownerId: ScheduleFixtures::STAFF_ID,
        );

        expect($business->overlaps($staffMember))->toBeTrue();
    });
});
