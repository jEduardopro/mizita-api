<?php

declare(strict_types=1);

use App\Domains\Availability\Exceptions\OverlappingScheduleIntervals;
use App\Domains\Availability\Services\WeeklySchedule;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Availability\ScheduleFixtures;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->schedule = new WeeklySchedule;

    $this->accepts = fn (array $rules) => expect(fn () => $this->schedule->refuseOverlaps($rules))
        ->not->toThrow(Throwable::class);

    $this->refuses = fn (array $rules) => expect(fn () => $this->schedule->refuseOverlaps($rules))
        ->toThrow(OverlappingScheduleIntervals::class);
});

describe('a week with nothing to collide', function () {
    it('accepts a business that has published no hours at all', function () {
        ($this->accepts)([]);
    });

    it('accepts a day with a single interval', function () {
        ($this->accepts)([ScheduleFixtures::rule()]);
    });

    it('accepts a closed day, which is simply a day with no interval', function () {
        ($this->accepts)([
            ScheduleFixtures::rule(weekday: Weekday::Monday),
            ScheduleFixtures::rule(id: ScheduleFixtures::SECOND_RULE_ID, weekday: Weekday::Wednesday),
        ]);
    });
});

describe('two intervals on the same day', function () {
    it('accepts a split shift whose halves touch at the boundary', function () {
        ($this->accepts)([
            ScheduleFixtures::rule(startsAt: '09:00', endsAt: '14:00'),
            ScheduleFixtures::rule(id: ScheduleFixtures::SECOND_RULE_ID, startsAt: '14:00', endsAt: '18:00'),
        ]);
    });

    it('accepts a split shift with a gap between its halves', function () {
        ($this->accepts)([
            ScheduleFixtures::rule(startsAt: '09:00', endsAt: '13:00'),
            ScheduleFixtures::rule(id: ScheduleFixtures::SECOND_RULE_ID, startsAt: '16:00', endsAt: '20:00'),
        ]);
    });

    it('refuses two intervals that share a single minute', function () {
        ($this->refuses)([
            ScheduleFixtures::rule(startsAt: '09:00', endsAt: '14:01'),
            ScheduleFixtures::rule(id: ScheduleFixtures::SECOND_RULE_ID, startsAt: '14:00', endsAt: '18:00'),
        ]);
    });

    it('refuses an interval swallowed whole by another', function () {
        ($this->refuses)([
            ScheduleFixtures::rule(startsAt: '09:00', endsAt: '18:00'),
            ScheduleFixtures::rule(id: ScheduleFixtures::SECOND_RULE_ID, startsAt: '12:00', endsAt: '13:00'),
        ]);
    });

    it('refuses the same interval sent twice', function () {
        ($this->refuses)([
            ScheduleFixtures::rule(startsAt: '09:00', endsAt: '14:00'),
            ScheduleFixtures::rule(id: ScheduleFixtures::SECOND_RULE_ID, startsAt: '09:00', endsAt: '14:00'),
        ]);
    });

    it('refuses two intervals that start together and end apart', function () {
        ($this->refuses)([
            ScheduleFixtures::rule(startsAt: '09:00', endsAt: '10:00'),
            ScheduleFixtures::rule(id: ScheduleFixtures::SECOND_RULE_ID, startsAt: '09:00', endsAt: '18:00'),
        ]);
    });
});

describe('more than two intervals on the same day', function () {
    it('accepts three shifts laid end to end', function () {
        ($this->accepts)([
            ScheduleFixtures::rule(startsAt: '09:00', endsAt: '13:00'),
            ScheduleFixtures::rule(id: ScheduleFixtures::SECOND_RULE_ID, startsAt: '13:00', endsAt: '14:00'),
            ScheduleFixtures::rule(id: ScheduleFixtures::THIRD_RULE_ID, startsAt: '14:00', endsAt: '18:00'),
        ]);
    });

    it('refuses a day whose first interval swallows the two that follow it', function () {
        ($this->refuses)([
            ScheduleFixtures::rule(startsAt: '09:00', endsAt: '18:00'),
            ScheduleFixtures::rule(id: ScheduleFixtures::SECOND_RULE_ID, startsAt: '10:00', endsAt: '11:00'),
            ScheduleFixtures::rule(id: ScheduleFixtures::THIRD_RULE_ID, startsAt: '12:00', endsAt: '13:00'),
        ]);
    });

    it('refuses a collision between the last two of three good-looking shifts', function () {
        ($this->refuses)([
            ScheduleFixtures::rule(startsAt: '08:00', endsAt: '09:00'),
            ScheduleFixtures::rule(id: ScheduleFixtures::SECOND_RULE_ID, startsAt: '10:00', endsAt: '14:00'),
            ScheduleFixtures::rule(id: ScheduleFixtures::THIRD_RULE_ID, startsAt: '13:30', endsAt: '18:00'),
        ]);
    });
});

describe('intervals that belong to different groups', function () {
    it('never collides two intervals sitting on different weekdays', function () {
        ($this->accepts)([
            ScheduleFixtures::rule(weekday: Weekday::Monday, startsAt: '09:00', endsAt: '18:00'),
            ScheduleFixtures::rule(
                id: ScheduleFixtures::SECOND_RULE_ID,
                weekday: Weekday::Tuesday,
                startsAt: '09:00',
                endsAt: '18:00',
            ),
        ]);
    });

    it('never collides a business with one of its staff members', function () {
        ($this->accepts)([
            ScheduleFixtures::rule(ownerType: ScheduleOwnerType::Business, startsAt: '09:00', endsAt: '18:00'),
            ScheduleFixtures::rule(
                id: ScheduleFixtures::SECOND_RULE_ID,
                ownerType: ScheduleOwnerType::StaffMember,
                ownerId: ScheduleFixtures::STAFF_ID,
                startsAt: '09:00',
                endsAt: '18:00',
            ),
        ]);
    });

    it('never collides two staff members with each other', function () {
        ($this->accepts)([
            ScheduleFixtures::rule(
                ownerType: ScheduleOwnerType::StaffMember,
                ownerId: ScheduleFixtures::STAFF_ID,
                startsAt: '09:00',
                endsAt: '18:00',
            ),
            ScheduleFixtures::rule(
                id: ScheduleFixtures::SECOND_RULE_ID,
                ownerType: ScheduleOwnerType::StaffMember,
                ownerId: ScheduleFixtures::OTHER_STAFF_ID,
                startsAt: '09:00',
                endsAt: '18:00',
            ),
        ]);
    });

    it('still collides two intervals of one staff member on one day', function () {
        ($this->refuses)([
            ScheduleFixtures::rule(
                ownerType: ScheduleOwnerType::StaffMember,
                ownerId: ScheduleFixtures::STAFF_ID,
                startsAt: '09:00',
                endsAt: '14:00',
            ),
            ScheduleFixtures::rule(
                id: ScheduleFixtures::SECOND_RULE_ID,
                ownerType: ScheduleOwnerType::StaffMember,
                ownerId: ScheduleFixtures::STAFF_ID,
                startsAt: '13:00',
                endsAt: '18:00',
            ),
        ]);
    });

    it('finds a collision hidden among intervals of other owners and other days', function () {
        ($this->refuses)([
            ScheduleFixtures::rule(weekday: Weekday::Tuesday, startsAt: '09:00', endsAt: '18:00'),
            ScheduleFixtures::rule(
                id: ScheduleFixtures::SECOND_RULE_ID,
                ownerType: ScheduleOwnerType::StaffMember,
                ownerId: ScheduleFixtures::STAFF_ID,
                weekday: Weekday::Monday,
                startsAt: '09:00',
                endsAt: '14:00',
            ),
            ScheduleFixtures::rule(
                id: ScheduleFixtures::THIRD_RULE_ID,
                ownerType: ScheduleOwnerType::StaffMember,
                ownerId: ScheduleFixtures::STAFF_ID,
                weekday: Weekday::Monday,
                startsAt: '10:00',
                endsAt: '11:00',
            ),
        ]);
    });
});

describe('the order the intervals arrive in', function () {
    it('reaches the same verdict on a good day however it was sorted', function () {
        $morning = ScheduleFixtures::rule(startsAt: '09:00', endsAt: '14:00');
        $afternoon = ScheduleFixtures::rule(id: ScheduleFixtures::SECOND_RULE_ID, startsAt: '14:00', endsAt: '18:00');

        ($this->accepts)([$morning, $afternoon]);
        ($this->accepts)([$afternoon, $morning]);
    });

    it('reaches the same verdict on a colliding day however it was sorted', function () {
        $morning = ScheduleFixtures::rule(startsAt: '09:00', endsAt: '15:00');
        $afternoon = ScheduleFixtures::rule(id: ScheduleFixtures::SECOND_RULE_ID, startsAt: '14:00', endsAt: '18:00');

        ($this->refuses)([$morning, $afternoon]);
        ($this->refuses)([$afternoon, $morning]);
    });

    it('leaves the list it was handed in the order it was handed', function () {
        $afternoon = ScheduleFixtures::rule(startsAt: '14:00', endsAt: '18:00');
        $morning = ScheduleFixtures::rule(id: ScheduleFixtures::SECOND_RULE_ID, startsAt: '09:00', endsAt: '14:00');
        $rules = [$afternoon, $morning];

        $this->schedule->refuseOverlaps($rules);

        expect($rules)->toBe([$afternoon, $morning]);
    });
});

describe('the refusal itself', function () {
    it('names the day the two intervals collided on', function () {
        expect(fn () => $this->schedule->refuseOverlaps([
            ScheduleFixtures::rule(weekday: Weekday::Saturday, startsAt: '09:00', endsAt: '15:00'),
            ScheduleFixtures::rule(
                id: ScheduleFixtures::SECOND_RULE_ID,
                weekday: Weekday::Saturday,
                startsAt: '14:00',
                endsAt: '18:00',
            ),
        ]))->toThrow(OverlappingScheduleIntervals::class, 'Two schedule intervals overlap on weekday [6].');
    });

    it('classifies a collision as a conflict the caller has to resolve', function () {
        $failure = OverlappingScheduleIntervals::onWeekday(Weekday::Monday->value);

        expect($failure->errorCode())->toBe('overlapping_schedule_intervals')
            ->and($failure->kind())->toBe(DomainFailureKind::Conflict);
    });
});

describe('what the service deliberately leaves alone', function () {
    it('groups by the owner alone, so two businesses never share a group by accident', function () {
        ($this->accepts)([
            ScheduleFixtures::rule(businessId: FakeBusinessContext::BUSINESS_ID, weekday: Weekday::Monday),
            ScheduleFixtures::rule(
                id: ScheduleFixtures::SECOND_RULE_ID,
                businessId: ScheduleFixtures::OTHER_BUSINESS_ID,
                ownerId: ScheduleFixtures::OTHER_BUSINESS_ID,
                weekday: Weekday::Monday,
            ),
        ]);
    });

    it('needs no clock, because recurring hours are local time facts with no date', function () {
        ($this->accepts)([ScheduleFixtures::rule(now: new DateTimeImmutable('2026-10-25T02:30:00+02:00'))]);
    });
});
