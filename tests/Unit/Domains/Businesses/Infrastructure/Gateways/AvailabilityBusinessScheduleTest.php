<?php

declare(strict_types=1);

use App\Domains\Availability\Application\UseCases\ReplaceSchedule;
use App\Domains\Availability\Entities\ScheduleRule;
use App\Domains\Availability\Exceptions\InvalidTimeOfDay;
use App\Domains\Availability\Exceptions\InvalidWeekday;
use App\Domains\Availability\Exceptions\OverlappingScheduleIntervals;
use App\Domains\Availability\Exceptions\ScheduleIntervalInverted;
use App\Domains\Availability\Services\WeeklySchedule;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Domains\Businesses\Contracts\BusinessSchedule;
use App\Domains\Businesses\Infrastructure\Gateways\AvailabilityBusinessSchedule;
use App\Domains\Businesses\ValueObjects\BusinessScheduleEntry;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Availability\FakeScheduleRuleRepository;
use Tests\Support\Availability\ScheduleFixtures;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;

function businessScheduleEntry(
    int $weekday = 1,
    string $startsAt = ScheduleFixtures::MORNING_START,
    string $endsAt = ScheduleFixtures::MORNING_END,
): BusinessScheduleEntry {
    return new BusinessScheduleEntry(weekday: $weekday, startsAt: $startsAt, endsAt: $endsAt);
}

beforeEach(function () {
    $this->rules = new FakeScheduleRuleRepository;

    $this->schedule = new AvailabilityBusinessSchedule(
        $this->rules,
        new ReplaceSchedule(
            $this->rules,
            new WeeklySchedule,
            new FakeBusinessContext,
            new FixedIdGenerator(
                ScheduleFixtures::RULE_ID,
                ScheduleFixtures::SECOND_RULE_ID,
                ScheduleFixtures::THIRD_RULE_ID,
            ),
            new FakeClock(ScheduleFixtures::now()),
        ),
    );

    $this->read = fn (): array => $this->schedule->forBusiness(FakeBusinessContext::BUSINESS_ID);

    $this->replace = fn (BusinessScheduleEntry ...$entries): mixed => $this->schedule->replaceForBusiness(
        FakeBusinessContext::BUSINESS_ID,
        array_values($entries),
    );
});

describe('reading the hours on file', function () {
    it('answers with an empty list when the business declared no hours', function () {
        expect(($this->read)())->toBe([]);
    });

    it('asks for the rules the business owns, under its uuid', function () {
        ($this->read)();

        expect($this->rules->reads)->toBe([[
            'ownerType' => ScheduleOwnerType::Business,
            'ownerId' => FakeBusinessContext::BUSINESS_ID,
        ]]);
    });

    it('translates each rule into the entry the business domain reads', function () {
        $this->rules->store(
            ScheduleOwnerType::Business,
            FakeBusinessContext::BUSINESS_ID,
            ScheduleFixtures::rule(weekday: Weekday::Tuesday, startsAt: '09:00', endsAt: '14:00'),
        );

        $entries = ($this->read)();

        expect($entries)->toHaveCount(1)
            ->and($entries[0])->toBeInstanceOf(BusinessScheduleEntry::class)
            ->and($entries[0]->weekday)->toBe(2)
            ->and($entries[0]->startsAt)->toBe('09:00')
            ->and($entries[0]->endsAt)->toBe('14:00');
    });

    it('writes every time as HH:mm, padded', function () {
        $this->rules->store(
            ScheduleOwnerType::Business,
            FakeBusinessContext::BUSINESS_ID,
            ScheduleFixtures::rule(startsAt: '9:05', endsAt: '18:00'),
        );

        expect(($this->read)()[0]->startsAt)->toBe('09:05');
    });

    it('drops the seconds a time column hands back', function () {
        $this->rules->store(
            ScheduleOwnerType::Business,
            FakeBusinessContext::BUSINESS_ID,
            ScheduleFixtures::rule(startsAt: '09:00:00', endsAt: '14:30:00'),
        );

        $entries = ($this->read)();

        expect($entries[0]->startsAt)->toBe('09:00')
            ->and($entries[0]->endsAt)->toBe('14:30');
    });

    it('reads the split shifts of a single weekday as separate entries', function () {
        $this->rules->store(
            ScheduleOwnerType::Business,
            FakeBusinessContext::BUSINESS_ID,
            ScheduleFixtures::rule(startsAt: '09:00', endsAt: '14:00'),
            ScheduleFixtures::rule(
                id: ScheduleFixtures::SECOND_RULE_ID,
                startsAt: '16:00',
                endsAt: '20:00',
            ),
        );

        expect(($this->read)())->toHaveCount(2);
    });
});

describe('replacing the hours a business submitted', function () {
    it('replaces the whole week under the business uuid, as business hours', function () {
        ($this->replace)(businessScheduleEntry());

        $replacement = $this->rules->replacements[0];

        expect($this->rules->replacements)->toHaveCount(1)
            ->and($replacement['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($replacement['ownerType'])->toBe(ScheduleOwnerType::Business)
            ->and($replacement['ownerId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($replacement['rules'])->toHaveCount(1);
    });

    it('converts the entry into a rule carrying the business uuid, never an internal key', function () {
        ($this->replace)(businessScheduleEntry(weekday: 3, startsAt: '09:30', endsAt: '13:45'));

        $rule = $this->rules->lastReplacement()[0];

        expect($rule)->toBeInstanceOf(ScheduleRule::class)
            ->and($rule->id)->toBe(ScheduleFixtures::RULE_ID)
            ->and($rule->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and(is_numeric($rule->businessId))->toBeFalse()
            ->and($rule->ownerType)->toBe(ScheduleOwnerType::Business)
            ->and($rule->ownerId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($rule->weekday)->toBe(Weekday::Wednesday)
            ->and($rule->startsAt()->toString())->toBe('09:30')
            ->and($rule->endsAt()->toString())->toBe('13:45')
            ->and($rule->createdAt)->toEqual(ScheduleFixtures::now());
    });

    it('closes the week when the business submits no entry at all', function () {
        ($this->replace)();

        expect($this->rules->replacements)->toHaveCount(1)
            ->and($this->rules->lastReplacement())->toBe([]);
    });

    it('accepts split shifts on one weekday', function () {
        ($this->replace)(
            businessScheduleEntry(startsAt: '09:00', endsAt: '14:00'),
            businessScheduleEntry(startsAt: '16:00', endsAt: '20:00'),
        );

        expect($this->rules->lastReplacement())->toHaveCount(2);
    });

    it('accepts back to back shifts, which touch without overlapping', function () {
        ($this->replace)(
            businessScheduleEntry(startsAt: ScheduleFixtures::MORNING_START, endsAt: ScheduleFixtures::MORNING_END),
            businessScheduleEntry(startsAt: ScheduleFixtures::AFTERNOON_START, endsAt: ScheduleFixtures::AFTERNOON_END),
        );

        expect($this->rules->lastReplacement())->toHaveCount(2);
    });

    it('accepts every day of the week', function (int $weekday, Weekday $expected) {
        ($this->replace)(businessScheduleEntry(weekday: $weekday));

        expect($this->rules->lastReplacement()[0]->weekday)->toBe($expected);
    })->with([
        'monday' => [1, Weekday::Monday],
        'saturday' => [6, Weekday::Saturday],
        'sunday' => [7, Weekday::Sunday],
    ]);

    it('accepts a time written with seconds and keeps the minute it means', function () {
        ($this->replace)(businessScheduleEntry(startsAt: '09:00:00', endsAt: '14:00:00'));

        expect($this->rules->lastReplacement()[0]->startsAt()->toString())->toBe('09:00');
    });
});

describe('refusing hours the domain cannot read', function () {
    it('refuses a weekday outside the week as a domain failure, never as a value error', function (int $weekday) {
        try {
            ($this->replace)(businessScheduleEntry(weekday: $weekday));
            $thrown = null;
        } catch (Throwable $escaped) {
            $thrown = $escaped;
        }

        expect($thrown)->toBeInstanceOf(InvalidWeekday::class)
            ->and($thrown)->toBeInstanceOf(DomainFailure::class)
            ->and($thrown)->not->toBeInstanceOf(ValueError::class)
            ->and($thrown->errorCode())->toBe('invalid_weekday')
            ->and($thrown->kind())->toBe(DomainFailureKind::Invalid)
            ->and($this->rules->replacements)->toBe([]);
    })->with([
        'zero, the ISO week has no day zero' => 0,
        'eight' => 8,
        'negative' => -1,
    ]);

    it('refuses a time the domain cannot read as a domain failure', function (string $startsAt) {
        try {
            ($this->replace)(businessScheduleEntry(startsAt: $startsAt));
            $thrown = null;
        } catch (Throwable $escaped) {
            $thrown = $escaped;
        }

        expect($thrown)->toBeInstanceOf(InvalidTimeOfDay::class)
            ->and($thrown)->toBeInstanceOf(DomainFailure::class)
            ->and($thrown->errorCode())->toBe('invalid_time_of_day')
            ->and($this->rules->replacements)->toBe([]);
    })->with([
        'empty' => '',
        'hour alone' => '9',
        'twenty five hundred' => '25:00',
        'sixty minutes' => '09:60',
        'words' => 'morning',
    ]);

    it('refuses an interval that ends before it starts', function () {
        expect(fn () => ($this->replace)(businessScheduleEntry(startsAt: '18:00', endsAt: '09:00')))
            ->toThrow(ScheduleIntervalInverted::class)
            ->and($this->rules->replacements)->toBe([]);
    });

    it('refuses an interval of no length at all', function () {
        expect(fn () => ($this->replace)(businessScheduleEntry(startsAt: '09:00', endsAt: '09:00')))
            ->toThrow(ScheduleIntervalInverted::class);
    });
});

describe('the rollback contract', function () {
    it('hands nothing back, so no use case response can cross the port', function () {
        expect(($this->replace)(businessScheduleEntry()))->toBeNull();
    });

    it('never declares a use case response on the port', function () {
        $returnTypes = array_map(
            static fn (ReflectionMethod $method): string => (string) $method->getReturnType(),
            (new ReflectionClass(BusinessSchedule::class))->getMethods(),
        );

        expect($returnTypes)->not->toContain(UseCaseResponse::class)
            ->and($returnTypes)->not->toContain('?'.UseCaseResponse::class);
    });

    it('declares void on the write the port exposes', function () {
        expect((string) (new ReflectionMethod(BusinessSchedule::class, 'replaceForBusiness'))->getReturnType())
            ->toBe('void');
    });

    it('rethrows the neighbour refusal itself, so the surrounding transaction rolls back', function () {
        try {
            ($this->replace)(
                businessScheduleEntry(startsAt: '09:00', endsAt: '14:00'),
                businessScheduleEntry(startsAt: '13:00', endsAt: '18:00'),
            );
            $thrown = null;
        } catch (Throwable $escaped) {
            $thrown = $escaped;
        }

        expect($thrown)->toBeInstanceOf(OverlappingScheduleIntervals::class)
            ->and($thrown)->toBeInstanceOf(DomainFailure::class)
            ->and($thrown->errorCode())->toBe('overlapping_schedule_intervals')
            ->and($thrown->kind())->toBe(DomainFailureKind::Conflict)
            ->and($this->rules->replacements)->toBe([]);
    });
});
