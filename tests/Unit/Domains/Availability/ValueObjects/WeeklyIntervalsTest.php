<?php

declare(strict_types=1);

use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Domains\Availability\ValueObjects\WeeklyIntervals;
use Tests\Support\Availability\ScheduleFixtures;
use Tests\Support\FakeBusinessContext;

function staffIntervals(array $intervalsByWeekday): WeeklyIntervals
{
    return ScheduleFixtures::weeklyIntervals(
        $intervalsByWeekday,
        ScheduleOwnerType::StaffMember,
        ScheduleFixtures::STAFF_ID,
    );
}

function businessIntervals(array $intervalsByWeekday): WeeklyIntervals
{
    return ScheduleFixtures::weeklyIntervals($intervalsByWeekday);
}

describe('the hours read off a set of rules', function () {
    it('keeps only the rules belonging to the owner it was asked about', function () {
        $intervals = WeeklyIntervals::fromRules(
            [
                ScheduleFixtures::rule(weekday: Weekday::Monday, startsAt: '09:00', endsAt: '14:00'),
                ScheduleFixtures::rule(
                    id: ScheduleFixtures::SECOND_RULE_ID,
                    ownerType: ScheduleOwnerType::StaffMember,
                    ownerId: ScheduleFixtures::STAFF_ID,
                    weekday: Weekday::Monday,
                    startsAt: '16:00',
                    endsAt: '20:00',
                ),
            ],
            ScheduleOwnerType::Business,
            FakeBusinessContext::BUSINESS_ID,
        );

        expect($intervals->forWeekday(Weekday::Monday))->toBe([[540, 840]]);
    });

    it('keeps only the rules of the one owner when two share an owner type', function () {
        $intervals = WeeklyIntervals::fromRules(
            [
                ScheduleFixtures::rule(
                    ownerType: ScheduleOwnerType::StaffMember,
                    ownerId: ScheduleFixtures::STAFF_ID,
                    weekday: Weekday::Monday,
                    startsAt: '09:00',
                    endsAt: '14:00',
                ),
                ScheduleFixtures::rule(
                    id: ScheduleFixtures::SECOND_RULE_ID,
                    ownerType: ScheduleOwnerType::StaffMember,
                    ownerId: ScheduleFixtures::OTHER_STAFF_ID,
                    weekday: Weekday::Monday,
                    startsAt: '16:00',
                    endsAt: '20:00',
                ),
            ],
            ScheduleOwnerType::StaffMember,
            ScheduleFixtures::STAFF_ID,
        );

        expect($intervals->forWeekday(Weekday::Monday))->toBe([[540, 840]]);
    });

    it('sorts the intervals of a split shift by the minute they open', function () {
        $intervals = businessIntervals([1 => [['16:00', '20:00'], ['09:00', '14:00']]]);

        expect($intervals->forWeekday(Weekday::Monday))->toBe([[540, 840], [960, 1200]]);
    });

    it('answers with no interval for a weekday nobody filled in', function () {
        expect(businessIntervals([1 => [['09:00', '14:00']]])->forWeekday(Weekday::Sunday))->toBe([]);
    });

    it('is empty only when it holds no rule at all', function () {
        expect(WeeklyIntervals::none()->isEmpty())->toBeTrue()
            ->and(businessIntervals([1 => [['09:00', '14:00']]])->isEmpty())->toBeFalse();
    });
});

describe('comparing two weeks', function () {
    it('equals a week whose weekdays were filled in another order', function () {
        $mondayFirst = staffIntervals([1 => [['09:00', '14:00']], 3 => [['10:00', '18:00']]]);
        $wednesdayFirst = staffIntervals([3 => [['10:00', '18:00']], 1 => [['09:00', '14:00']]]);

        expect($mondayFirst->equals($wednesdayFirst))->toBeTrue()
            ->and($wednesdayFirst->equals($mondayFirst))->toBeTrue();
    });

    it('equals a week whose split shift was filled in another order', function () {
        $morningFirst = staffIntervals([1 => [['09:00', '14:00'], ['16:00', '20:00']]]);
        $afternoonFirst = staffIntervals([1 => [['16:00', '20:00'], ['09:00', '14:00']]]);

        expect($morningFirst->equals($afternoonFirst))->toBeTrue();
    });

    it('equals a week built from rules with other ids and timestamps', function () {
        $rule = fn (string $id, string $now) => ScheduleFixtures::rule(
            id: $id,
            ownerType: ScheduleOwnerType::StaffMember,
            ownerId: ScheduleFixtures::STAFF_ID,
            weekday: Weekday::Friday,
            startsAt: '10:00',
            endsAt: '12:00',
            now: new DateTimeImmutable($now),
        );

        $stored = WeeklyIntervals::fromRules(
            [$rule(ScheduleFixtures::RULE_ID, '2025-06-01T08:00:00+00:00')],
            ScheduleOwnerType::StaffMember,
            ScheduleFixtures::STAFF_ID,
        );
        $submitted = WeeklyIntervals::fromRules(
            [$rule(ScheduleFixtures::GENERATED_RULE_ID, '2026-01-01T12:00:00+00:00')],
            ScheduleOwnerType::StaffMember,
            ScheduleFixtures::STAFF_ID,
        );

        expect($stored->equals($submitted))->toBeTrue();
    });

    it('equals another empty week', function () {
        expect(WeeklyIntervals::none()->equals(staffIntervals([])))->toBeTrue();
    });

    it('differs from a week that is not the same', function (array $one, array $other) {
        expect(staffIntervals($one)->equals(staffIntervals($other)))->toBeFalse()
            ->and(staffIntervals($other)->equals(staffIntervals($one)))->toBeFalse();
    })->with([
        'another weekday' => [[1 => [['09:00', '14:00']]], [2 => [['09:00', '14:00']]]],
        'a later opening' => [[1 => [['09:00', '14:00']]], [1 => [['09:30', '14:00']]]],
        'a later closing' => [[1 => [['09:00', '14:00']]], [1 => [['09:00', '15:00']]]],
        'one more interval on the day' => [[1 => [['09:00', '14:00']]], [1 => [['09:00', '14:00'], ['16:00', '20:00']]]],
        'one more weekday' => [[1 => [['09:00', '14:00']]], [1 => [['09:00', '14:00']], 6 => [['10:00', '13:00']]]],
        'an empty week' => [[], [1 => [['09:00', '14:00']]]],
    ]);
});

describe('a staff member who set no hours of their own', function () {
    it('inherits the business hours whole when it has no rule at all', function () {
        $business = businessIntervals([
            1 => [['09:00', '14:00']],
            3 => [['10:00', '18:00']],
        ]);

        $effective = WeeklyIntervals::none()->orInheritedFrom($business);

        expect($effective)->toBe($business)
            ->and($effective->forWeekday(Weekday::Monday))->toBe([[540, 840]])
            ->and($effective->forWeekday(Weekday::Wednesday))->toBe([[600, 1080]]);
    });

    it('inherits nothing when the business itself keeps no hours', function () {
        expect(WeeklyIntervals::none()->orInheritedFrom(WeeklyIntervals::none())->isEmpty())->toBeTrue();
    });
});

describe('a staff member who set any hours of their own', function () {
    it('keeps its own hours rather than inheriting the wider ones', function () {
        $staff = staffIntervals([1 => [['10:00', '12:00']]]);

        $effective = $staff->orInheritedFrom(businessIntervals([1 => [['09:00', '18:00']]]));

        expect($effective)->toBe($staff)
            ->and($effective->forWeekday(Weekday::Monday))->toBe([[600, 720]]);
    });

    it('keeps a weekday it left empty empty, rather than inheriting that day alone', function () {
        $staff = staffIntervals([1 => [['10:00', '12:00']]]);
        $business = businessIntervals([
            1 => [['09:00', '18:00']],
            3 => [['09:00', '18:00']],
        ]);

        $effective = $staff->orInheritedFrom($business);

        expect($effective->forWeekday(Weekday::Wednesday))->toBe([]);
    });

    it('keeps its own hours even where they run outside the business hours', function () {
        $staff = staffIntervals([1 => [['08:00', '20:00']]]);

        $effective = $staff->orInheritedFrom(businessIntervals([1 => [['09:00', '14:00']]]));

        expect($effective->forWeekday(Weekday::Monday))->toBe([[480, 1200]]);
    });

    it('keeps its own hours on a weekday the business is closed', function () {
        $staff = staffIntervals([7 => [['10:00', '12:00']]]);

        $effective = $staff->orInheritedFrom(businessIntervals([1 => [['09:00', '14:00']]]));

        expect($effective->forWeekday(Weekday::Sunday))->toBe([[600, 720]])
            ->and($effective->forWeekday(Weekday::Monday))->toBe([]);
    });
});
