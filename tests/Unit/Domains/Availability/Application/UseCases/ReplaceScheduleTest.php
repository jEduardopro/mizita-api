<?php

declare(strict_types=1);

use App\Domains\Availability\Application\Dtos\ScheduleRuleData;
use App\Domains\Availability\Application\UseCases\ReplaceSchedule;
use App\Domains\Availability\Entities\ScheduleRule;
use App\Domains\Availability\Exceptions\ScheduleIntervalInverted;
use App\Domains\Availability\Services\WeeklySchedule;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Availability\FakeScheduleRuleRepository;
use Tests\Support\Availability\ScheduleFixtures;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;

beforeEach(function () {
    $this->rules = new FakeScheduleRuleRepository;

    $this->useCase = new ReplaceSchedule(
        $this->rules,
        new WeeklySchedule,
        new FakeBusinessContext,
        new FixedIdGenerator(
            ScheduleFixtures::RULE_ID,
            ScheduleFixtures::SECOND_RULE_ID,
            ScheduleFixtures::THIRD_RULE_ID,
        ),
        new FakeClock(ScheduleFixtures::now()),
    );

    $this->replace = fn (...$overrides) => $this->useCase->handle(ScheduleFixtures::input(...$overrides));
});

describe('publishing a week of hours', function () {
    it('answers with the hours it stored, one entry per interval', function () {
        $data = ($this->replace)(intervals: [
            ScheduleFixtures::interval(Weekday::Monday, '09:00', '14:00'),
            ScheduleFixtures::interval(Weekday::Monday, '16:00', '20:00'),
        ])->value();

        expect($data)->toHaveCount(2)
            ->and($data[0])->toBeInstanceOf(ScheduleRuleData::class)
            ->and($data[0]->id)->toBe(ScheduleFixtures::RULE_ID)
            ->and($data[0]->weekday)->toBe(1)
            ->and($data[0]->startsAt)->toBe('09:00')
            ->and($data[0]->endsAt)->toBe('14:00')
            ->and($data[1]->id)->toBe(ScheduleFixtures::SECOND_RULE_ID)
            ->and($data[1]->startsAt)->toBe('16:00')
            ->and($data[1]->endsAt)->toBe('20:00');
    });

    it('hands back the uuid the identity generated, never a row number', function () {
        $data = ($this->replace)()->value();

        expect($data[0]->id)->toBe(ScheduleFixtures::RULE_ID)
            ->and($data[0]->id)->toBeString();
    });

    it('gives each interval an identity of its own', function () {
        $data = ($this->replace)(intervals: [
            ScheduleFixtures::interval(Weekday::Monday),
            ScheduleFixtures::interval(Weekday::Tuesday),
            ScheduleFixtures::interval(Weekday::Wednesday),
        ])->value();

        expect(array_column($data, 'id'))->toBe([
            ScheduleFixtures::RULE_ID,
            ScheduleFixtures::SECOND_RULE_ID,
            ScheduleFixtures::THIRD_RULE_ID,
        ]);
    });

    it('keeps the order the week was sent in', function () {
        $data = ($this->replace)(intervals: [
            ScheduleFixtures::interval(Weekday::Sunday, '10:00', '14:00'),
            ScheduleFixtures::interval(Weekday::Monday, '09:00', '14:00'),
        ])->value();

        expect(array_column($data, 'weekday'))->toBe([7, 1]);
    });

    it('replaces the whole week in one call to the repository', function () {
        ($this->replace)();

        expect($this->rules->replacements)->toHaveCount(1)
            ->and($this->rules->lastReplacement())->toHaveCount(1);
    });

    it('stamps every rule with the instant the clock reported', function () {
        ($this->replace)(intervals: [
            ScheduleFixtures::interval(Weekday::Monday),
            ScheduleFixtures::interval(Weekday::Tuesday),
        ]);

        foreach ($this->rules->lastReplacement() as $rule) {
            expect($rule)->toBeInstanceOf(ScheduleRule::class)
                ->and($rule->createdAt)->toEqual(ScheduleFixtures::now());
        }
    });

    it('closes a day by sending no interval for it', function () {
        $response = ($this->replace)(intervals: []);

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBe([])
            ->and($this->rules->replacements)->toHaveCount(1)
            ->and($this->rules->lastReplacement())->toBe([]);
    });
});

describe('the business the hours belong to', function () {
    it('scopes every rule to the business the caller is operating', function () {
        ($this->replace)();

        expect($this->rules->lastReplacement()[0]->businessId)->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('tells the repository which business to replace the hours in', function () {
        ($this->replace)();

        expect($this->rules->replacements[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('never reaches for a business the input tried to name', function () {
        $useCase = new ReplaceSchedule(
            $this->rules,
            new WeeklySchedule,
            new FakeBusinessContext(ScheduleFixtures::OTHER_BUSINESS_ID),
            new FixedIdGenerator(ScheduleFixtures::RULE_ID),
            new FakeClock(ScheduleFixtures::now()),
        );

        $useCase->handle(ScheduleFixtures::input());

        expect($this->rules->lastReplacement()[0]->businessId)->toBe(ScheduleFixtures::OTHER_BUSINESS_ID)
            ->and($this->rules->replacements[0]['businessId'])->toBe(ScheduleFixtures::OTHER_BUSINESS_ID);
    });
});

describe('the owner the hours belong to', function () {
    it('hands the owner it was asked about straight to the repository', function () {
        ($this->replace)(ownerType: ScheduleOwnerType::StaffMember, ownerId: ScheduleFixtures::STAFF_ID);

        expect($this->rules->replacements[0]['ownerType'])->toBe(ScheduleOwnerType::StaffMember)
            ->and($this->rules->replacements[0]['ownerId'])->toBe(ScheduleFixtures::STAFF_ID);
    });

    it('marks every rule with that same owner', function () {
        ($this->replace)(
            ownerType: ScheduleOwnerType::StaffMember,
            ownerId: ScheduleFixtures::STAFF_ID,
            intervals: [
                ScheduleFixtures::interval(Weekday::Monday),
                ScheduleFixtures::interval(Weekday::Tuesday),
            ],
        );

        foreach ($this->rules->lastReplacement() as $rule) {
            expect($rule->ownerType)->toBe(ScheduleOwnerType::StaffMember)
                ->and($rule->ownerId)->toBe(ScheduleFixtures::STAFF_ID);
        }
    });

    it('names the owner with a uuid, never with a row number', function () {
        ($this->replace)(ownerType: ScheduleOwnerType::StaffMember, ownerId: ScheduleFixtures::STAFF_ID);

        expect($this->rules->replacements[0]['ownerId'])->toBe(ScheduleFixtures::STAFF_ID)
            ->and($this->rules->replacements[0]['ownerId'])->toBeString();
    });
});

describe('hours it cannot accept', function () {
    it('refuses a day whose two intervals overlap', function () {
        $response = ($this->replace)(intervals: [
            ScheduleFixtures::interval(Weekday::Monday, '09:00', '15:00'),
            ScheduleFixtures::interval(Weekday::Monday, '14:00', '18:00'),
        ]);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('overlapping_schedule_intervals')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('stores nothing when two intervals overlap', function () {
        ($this->replace)(intervals: [
            ScheduleFixtures::interval(Weekday::Monday, '09:00', '15:00'),
            ScheduleFixtures::interval(Weekday::Monday, '14:00', '18:00'),
        ]);

        expect($this->rules->replacements)->toBe([]);
    });

    it('accepts a split shift whose halves only touch', function () {
        $response = ($this->replace)(intervals: [
            ScheduleFixtures::interval(Weekday::Monday, '09:00', '14:00'),
            ScheduleFixtures::interval(Weekday::Monday, '14:00', '18:00'),
        ]);

        expect($response->succeeded())->toBeTrue()
            ->and($this->rules->lastReplacement())->toHaveCount(2);
    });

    it('refuses an interval that ends before it starts', function () {
        $response = ($this->replace)(intervals: [ScheduleFixtures::interval(Weekday::Monday, '18:00', '09:00')]);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('schedule_interval_inverted')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid);
    });

    it('stores nothing when an interval ends before it starts', function () {
        ($this->replace)(intervals: [
            ScheduleFixtures::interval(Weekday::Monday, '09:00', '14:00'),
            ScheduleFixtures::interval(Weekday::Tuesday, '18:00', '09:00'),
        ]);

        expect($this->rules->replacements)->toBe([]);
    });

    it('returns the refusal instead of throwing it at the caller', function () {
        $response = ($this->replace)(intervals: [ScheduleFixtures::interval(Weekday::Monday, '18:00', '09:00')]);

        expect($response)->toBeInstanceOf(UseCaseResponse::class)
            ->and($response->failed())->toBeTrue()
            ->and($response->error()->cause())->toBeInstanceOf(ScheduleIntervalInverted::class);
    });
});
