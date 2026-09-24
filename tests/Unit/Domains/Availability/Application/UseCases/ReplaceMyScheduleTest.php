<?php

declare(strict_types=1);

use App\Domains\Availability\Application\Dtos\MyScheduleData;
use App\Domains\Availability\Application\Dtos\ReplaceMyScheduleInput;
use App\Domains\Availability\Application\Dtos\ScheduleRuleData;
use App\Domains\Availability\Application\UseCases\ReplaceMySchedule;
use App\Domains\Availability\Contracts\ScheduleRuleRepository;
use App\Domains\Availability\Entities\ScheduleRule;
use App\Domains\Availability\Exceptions\ScheduleIntervalInverted;
use App\Domains\Availability\Services\WeeklySchedule;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Availability\FakeScheduleRuleRepository;
use Tests\Support\Availability\FakeStaffMembership;
use Tests\Support\Availability\ScheduleFixtures;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;

beforeEach(function () {
    $this->accountId = '01930000-0000-7000-8000-0000000000a1';
    $this->strangerAccountId = '01930000-0000-7000-8000-0000000000a9';
    $this->ruleIds = [ScheduleFixtures::RULE_ID, ScheduleFixtures::SECOND_RULE_ID, ScheduleFixtures::THIRD_RULE_ID];
    $this->spareId = ScheduleFixtures::GENERATED_RULE_ID;

    $this->memberships = (new FakeStaffMembership)
        ->grant(FakeBusinessContext::BUSINESS_ID, $this->accountId, ScheduleFixtures::STAFF_ID);
    $this->rules = new FakeScheduleRuleRepository;
    $this->transactions = new FakeTransactionManager;
    $this->ids = new FixedIdGenerator(...[...$this->ruleIds, $this->spareId]);
    $this->clock = new FakeClock(ScheduleFixtures::now());

    $this->businessRules = [
        ScheduleFixtures::rule(
            id: '01930000-0000-7000-8000-0000000000c1',
            weekday: Weekday::Monday,
            startsAt: '09:00',
            endsAt: '18:00',
        ),
        ScheduleFixtures::rule(
            id: '01930000-0000-7000-8000-0000000000c2',
            weekday: Weekday::Tuesday,
            startsAt: '10:00',
            endsAt: '14:00',
        ),
    ];
    $this->rules->store(ScheduleOwnerType::Business, FakeBusinessContext::BUSINESS_ID, ...$this->businessRules);

    $this->entry = fn (int $weekday = 1, string $startsAt = '09:00', string $endsAt = '14:00') => [
        'weekday' => $weekday,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
    ];

    $this->useCaseWith = fn (
        ?ScheduleRuleRepository $rules = null,
        ?FakeBusinessContext $business = null,
    ): ReplaceMySchedule => new ReplaceMySchedule(
        $this->memberships,
        $rules ?? $this->rules,
        new WeeklySchedule,
        $business ?? new FakeBusinessContext,
        $this->transactions,
        $this->ids,
        $this->clock,
    );

    $this->replace = fn (array $payload, ?string $accountId = null, mixed ...$dependencies) => ($this->useCaseWith)(...$dependencies)
        ->handle(ReplaceMyScheduleInput::fromRequest($payload, $accountId ?? $this->accountId));
});

describe('replacing the staff member\'s own week', function () {
    it('answers with the rules it stored, entry by entry', function () {
        $data = ($this->replace)(['schedule' => [
            ($this->entry)(1, '09:00', '14:00'),
            ($this->entry)(1, '16:00', '20:00'),
            ($this->entry)(6, '10:00', '13:30'),
        ]])->value();

        expect($data)->toBeInstanceOf(MyScheduleData::class)
            ->and($data->schedule)->toHaveCount(3)
            ->and($data->schedule)->each->toBeInstanceOf(ScheduleRuleData::class)
            ->and($data->schedule[0]->id)->toBe(ScheduleFixtures::RULE_ID)
            ->and($data->schedule[0]->weekday)->toBe(1)
            ->and($data->schedule[0]->startsAt)->toBe('09:00')
            ->and($data->schedule[0]->endsAt)->toBe('14:00')
            ->and($data->schedule[1]->id)->toBe(ScheduleFixtures::SECOND_RULE_ID)
            ->and($data->schedule[1]->weekday)->toBe(1)
            ->and($data->schedule[1]->startsAt)->toBe('16:00')
            ->and($data->schedule[1]->endsAt)->toBe('20:00')
            ->and($data->schedule[2]->id)->toBe(ScheduleFixtures::THIRD_RULE_ID)
            ->and($data->schedule[2]->weekday)->toBe(6)
            ->and($data->schedule[2]->startsAt)->toBe('10:00')
            ->and($data->schedule[2]->endsAt)->toBe('13:30');
    });

    it('flags the answer as their own rather than inherited', function () {
        expect(($this->replace)(['schedule' => [($this->entry)()]])->value()->inherited)->toBeFalse();
    });

    it('files every rule under the staff member the membership resolved to', function () {
        ($this->replace)(['schedule' => [($this->entry)(1), ($this->entry)(2)]]);

        expect($this->rules->replacements)->toHaveCount(1)
            ->and($this->rules->replacements[0]['ownerType'])->toBe(ScheduleOwnerType::StaffMember)
            ->and($this->rules->replacements[0]['ownerId'])->toBe(ScheduleFixtures::STAFF_ID);

        foreach ($this->rules->lastReplacement() as $rule) {
            expect($rule)->toBeInstanceOf(ScheduleRule::class)
                ->and($rule->ownerType)->toBe(ScheduleOwnerType::StaffMember)
                ->and($rule->ownerId)->toBe(ScheduleFixtures::STAFF_ID);
        }
    });

    it('leaves the business hours untouched', function () {
        ($this->replace)(['schedule' => [($this->entry)()]]);

        expect(array_column($this->rules->replacements, 'ownerType'))->not->toContain(ScheduleOwnerType::Business)
            ->and($this->rules->allForOwner(ScheduleOwnerType::Business, FakeBusinessContext::BUSINESS_ID))
            ->toBe($this->businessRules);
    });

    it('writes inside one transaction', function () {
        $wroteInsideTransaction = null;
        $rules = Mockery::mock(ScheduleRuleRepository::class);
        $rules->shouldReceive('replaceForOwner')->andReturnUsing(function () use (&$wroteInsideTransaction) {
            $wroteInsideTransaction = $this->transactions->isRunning();
        });

        ($this->replace)(['schedule' => [($this->entry)()]], null, $rules);

        expect($this->transactions->runs())->toBe(1)
            ->and($wroteInsideTransaction)->toBeTrue();
    });

    it('accepts staff hours that fall outside the business hours', function () {
        $response = ($this->replace)(['schedule' => [
            ($this->entry)(1, '06:00', '23:30'),
            ($this->entry)(7, '08:00', '12:00'),
        ]]);

        expect($response->succeeded())->toBeTrue()
            ->and($this->rules->lastReplacement())->toHaveCount(2)
            ->and($this->rules->lastReplacement()[0]->startsAt()->toString())->toBe('06:00')
            ->and($this->rules->lastReplacement()[1]->weekday)->toBe(Weekday::Sunday);
    });

    it('accepts a split shift whose halves only touch', function () {
        $response = ($this->replace)(['schedule' => [
            ($this->entry)(1, '09:00', '14:00'),
            ($this->entry)(1, '14:00', '18:00'),
        ]]);

        expect($response->succeeded())->toBeTrue()
            ->and($this->rules->lastReplacement())->toHaveCount(2);
    });

    it('accepts the same hours on different weekdays', function () {
        $response = ($this->replace)(['schedule' => [
            ($this->entry)(1, '09:00', '14:00'),
            ($this->entry)(2, '09:00', '14:00'),
        ]]);

        expect($response->succeeded())->toBeTrue();
    });

    it('accepts the widest day the clock allows', function () {
        $response = ($this->replace)(['schedule' => [($this->entry)(7, '00:00', '23:59')]]);

        expect($response->value()->schedule[0]->startsAt)->toBe('00:00')
            ->and($response->value()->schedule[0]->endsAt)->toBe('23:59');
    });

    it('replaces whatever hours the staff member had before', function () {
        $this->rules->store(
            ScheduleOwnerType::StaffMember,
            ScheduleFixtures::STAFF_ID,
            ScheduleFixtures::rule(
                id: '01930000-0000-7000-8000-0000000000e1',
                ownerType: ScheduleOwnerType::StaffMember,
                ownerId: ScheduleFixtures::STAFF_ID,
                weekday: Weekday::Friday,
            ),
        );

        ($this->replace)(['schedule' => [($this->entry)(3, '11:00', '15:00')]]);

        $stored = $this->rules->allForOwner(ScheduleOwnerType::StaffMember, ScheduleFixtures::STAFF_ID);

        expect($stored)->toHaveCount(1)
            ->and($stored[0]->id)->toBe(ScheduleFixtures::RULE_ID)
            ->and($stored[0]->weekday)->toBe(Weekday::Wednesday);
    });
});

describe('identities and timestamps', function () {
    it('draws each rule id from the identity generator, in order', function () {
        $data = ($this->replace)(['schedule' => [($this->entry)(1), ($this->entry)(2), ($this->entry)(3)]])->value();

        expect(array_column($data->schedule, 'id'))->toBe($this->ruleIds)
            ->and(array_map(static fn (ScheduleRule $rule): string => $rule->id, $this->rules->lastReplacement()))
            ->toBe($this->ruleIds);
    });

    it('draws exactly one identity per interval and no more', function () {
        ($this->replace)(['schedule' => [($this->entry)(1), ($this->entry)(2), ($this->entry)(3)]]);

        expect($this->ids->next())->toBe($this->spareId);
    });

    it('hands back uuids, never row numbers', function () {
        $ids = array_column(($this->replace)(['schedule' => [($this->entry)()]])->value()->schedule, 'id');

        expect(array_filter($ids, is_numeric(...)))->toBe([])
            ->and($ids)->each->toBeString();
    });

    it('stamps every rule with the instant the clock reported', function () {
        $this->clock->advance('P3DT4H');

        ($this->replace)(['schedule' => [($this->entry)(1), ($this->entry)(2)]]);

        foreach ($this->rules->lastReplacement() as $rule) {
            expect($rule->createdAt)->toEqual(new DateTimeImmutable('2026-01-04T16:00:00+00:00'));
        }
    });
});

describe('the business the schedule is replaced in', function () {
    beforeEach(function () {
        $this->memberships->grant(ScheduleFixtures::OTHER_BUSINESS_ID, $this->accountId, ScheduleFixtures::OTHER_STAFF_ID);
        $this->otherBusiness = new FakeBusinessContext(ScheduleFixtures::OTHER_BUSINESS_ID);
    });

    it('resolves the membership inside the business the caller is operating', function () {
        ($this->replace)(['schedule' => [($this->entry)()]], null, null, $this->otherBusiness);

        expect($this->memberships->lookups)->toBe([[
            'businessId' => ScheduleFixtures::OTHER_BUSINESS_ID,
            'accountId' => $this->accountId,
        ]]);
    });

    it('scopes the write and every rule to that business', function () {
        ($this->replace)(['schedule' => [($this->entry)(1), ($this->entry)(2)]], null, null, $this->otherBusiness);

        expect($this->rules->replacements[0]['businessId'])->toBe(ScheduleFixtures::OTHER_BUSINESS_ID)
            ->and($this->rules->replacements[0]['ownerId'])->toBe(ScheduleFixtures::OTHER_STAFF_ID);

        foreach ($this->rules->lastReplacement() as $rule) {
            expect($rule->businessId)->toBe(ScheduleFixtures::OTHER_BUSINESS_ID);
        }
    });

    it('never writes into the business of another membership the account holds', function () {
        ($this->replace)(['schedule' => [($this->entry)()]], null, null, $this->otherBusiness);

        expect(array_column($this->rules->replacements, 'businessId'))->toBe([ScheduleFixtures::OTHER_BUSINESS_ID])
            ->and(array_column($this->rules->replacements, 'ownerId'))->not->toContain(ScheduleFixtures::STAFF_ID);
    });
});

describe('an empty schedule', function () {
    beforeEach(function () {
        $this->rules->store(
            ScheduleOwnerType::StaffMember,
            ScheduleFixtures::STAFF_ID,
            ScheduleFixtures::rule(
                id: '01930000-0000-7000-8000-0000000000e1',
                ownerType: ScheduleOwnerType::StaffMember,
                ownerId: ScheduleFixtures::STAFF_ID,
                weekday: Weekday::Friday,
            ),
        );
    });

    it('removes every rule the staff member had', function () {
        ($this->replace)(['schedule' => []]);

        expect($this->rules->replacements)->toHaveCount(1)
            ->and($this->rules->replacements[0]['ownerType'])->toBe(ScheduleOwnerType::StaffMember)
            ->and($this->rules->replacements[0]['ownerId'])->toBe(ScheduleFixtures::STAFF_ID)
            ->and($this->rules->lastReplacement())->toBe([])
            ->and($this->rules->allForOwner(ScheduleOwnerType::StaffMember, ScheduleFixtures::STAFF_ID))->toBe([]);
    });

    it('answers with the business hours, flagged as inherited', function () {
        $data = ($this->replace)(['schedule' => []])->value();

        expect($data->inherited)->toBeTrue()
            ->and($data->schedule)->toHaveCount(2)
            ->and($data->schedule[0]->id)->toBe('01930000-0000-7000-8000-0000000000c1')
            ->and($data->schedule[0]->weekday)->toBe(1)
            ->and($data->schedule[0]->startsAt)->toBe('09:00')
            ->and($data->schedule[0]->endsAt)->toBe('18:00')
            ->and($data->schedule[1]->id)->toBe('01930000-0000-7000-8000-0000000000c2')
            ->and($data->schedule[1]->weekday)->toBe(2)
            ->and($data->schedule[1]->startsAt)->toBe('10:00')
            ->and($data->schedule[1]->endsAt)->toBe('14:00');
    });

    it('reads the business hours only after the staff rules were removed', function () {
        ($this->replace)(['schedule' => []]);

        expect($this->rules->reads)->toBe([[
            'ownerType' => ScheduleOwnerType::Business,
            'ownerId' => FakeBusinessContext::BUSINESS_ID,
        ]]);
    });

    it('answers with an empty inherited week when the business has no hours either', function () {
        $this->memberships->grant(ScheduleFixtures::OTHER_BUSINESS_ID, $this->accountId, ScheduleFixtures::OTHER_STAFF_ID);

        $data = ($this->replace)(['schedule' => []], null, null, new FakeBusinessContext(ScheduleFixtures::OTHER_BUSINESS_ID))->value();

        expect($data->inherited)->toBeTrue()
            ->and($data->schedule)->toBe([]);
    });

    it('draws no identity', function () {
        ($this->replace)(['schedule' => []]);

        expect($this->ids->next())->toBe(ScheduleFixtures::RULE_ID);
    });
});

describe('a schedule it cannot accept', function () {
    it('returns the refusal with its code and kind', function (array $payload, string $code, DomainFailureKind $kind) {
        $response = ($this->replace)($payload);

        expect($response)->toBeInstanceOf(UseCaseResponse::class)
            ->and($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe($kind);
    })->with('refused my schedules');

    it('writes nothing and opens no transaction when it refuses', function (array $payload) {
        ($this->replace)($payload);

        expect($this->rules->replacements)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
    })->with('refused my schedules');

    it('refuses the whole week when only its last interval is inverted', function () {
        $response = ($this->replace)(['schedule' => [
            ($this->entry)(1, '09:00', '14:00'),
            ($this->entry)(2, '18:00', '09:00'),
        ]]);

        expect($response->error()->cause())->toBeInstanceOf(ScheduleIntervalInverted::class)
            ->and($this->rules->replacements)->toBe([]);
    });

    it('validates the payload before asking about the membership', function () {
        $response = ($this->replace)(['schedule' => [($this->entry)(9)]], $this->strangerAccountId);

        expect($response->error()->code)->toBe('invalid_weekday')
            ->and($this->memberships->lookups)->toBe([]);
    });
});

describe('a caller who is not a staff member of the business', function () {
    it('refuses an account with no membership', function () {
        $response = ($this->replace)(['schedule' => [($this->entry)()]], $this->strangerAccountId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_accessible')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden);
    });

    it('refuses an account whose membership belongs to another business', function () {
        $this->memberships->grant(ScheduleFixtures::OTHER_BUSINESS_ID, $this->strangerAccountId, ScheduleFixtures::OTHER_STAFF_ID);

        $response = ($this->replace)(['schedule' => [($this->entry)()]], $this->strangerAccountId);

        expect($response->error()->code)->toBe('business_not_accessible');
    });

    it('writes nothing, draws no identity and reads no hours for a caller it refused', function () {
        ($this->replace)(['schedule' => [($this->entry)()]], $this->strangerAccountId);

        expect($this->rules->replacements)->toBe([])
            ->and($this->rules->reads)->toBe([])
            ->and($this->transactions->runs())->toBe(0)
            ->and($this->ids->next())->toBe(ScheduleFixtures::RULE_ID);
    });

    it('refuses an empty schedule too, so a stranger cannot wipe anyone\'s hours', function () {
        $response = ($this->replace)(['schedule' => []], $this->strangerAccountId);

        expect($response->error()->code)->toBe('business_not_accessible')
            ->and($this->rules->replacements)->toBe([]);
    });
});

dataset('refused my schedules', function () {
    $entry = fn (mixed $weekday = 1, mixed $startsAt = '09:00', mixed $endsAt = '14:00') => [
        'weekday' => $weekday,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
    ];

    return [
        'the schedule key is missing' => [[], 'schedule_not_submitted', DomainFailureKind::Invalid],
        'the schedule is null' => [['schedule' => null], 'schedule_not_submitted', DomainFailureKind::Invalid],
        'the schedule is not a list' => [['schedule' => 'monday'], 'schedule_not_submitted', DomainFailureKind::Invalid],
        'weekday zero' => [['schedule' => [$entry(0)]], 'invalid_weekday', DomainFailureKind::Invalid],
        'weekday eight' => [['schedule' => [$entry(8)]], 'invalid_weekday', DomainFailureKind::Invalid],
        'weekday missing' => [['schedule' => [['starts_at' => '09:00', 'ends_at' => '14:00']]], 'invalid_weekday', DomainFailureKind::Invalid],
        'weekday as a name' => [['schedule' => [$entry('monday')]], 'invalid_weekday', DomainFailureKind::Invalid],
        'fractional weekday' => [['schedule' => [$entry(3.7)]], 'invalid_weekday', DomainFailureKind::Invalid],
        'entry is not an object' => [['schedule' => ['monday']], 'invalid_weekday', DomainFailureKind::Invalid],
        'start hour out of range' => [['schedule' => [$entry(1, '24:00')]], 'invalid_time_of_day', DomainFailureKind::Invalid],
        'end minute out of range' => [['schedule' => [$entry(1, '09:00', '14:60')]], 'invalid_time_of_day', DomainFailureKind::Invalid],
        'malformed start' => [['schedule' => [$entry(1, 'nine')]], 'invalid_time_of_day', DomainFailureKind::Invalid],
        'unpadded start hour' => [['schedule' => [$entry(1, '9:00')]], 'invalid_time_of_day', DomainFailureKind::Invalid],
        'end with seconds' => [['schedule' => [$entry(1, '09:00', '14:00:00')]], 'invalid_time_of_day', DomainFailureKind::Invalid],
        'blank end' => [['schedule' => [$entry(1, '09:00', '   ')]], 'invalid_time_of_day', DomainFailureKind::Invalid],
        'start missing' => [['schedule' => [['weekday' => 1, 'ends_at' => '14:00']]], 'invalid_time_of_day', DomainFailureKind::Invalid],
        'end as a number' => [['schedule' => [$entry(1, '09:00', 1400)]], 'invalid_time_of_day', DomainFailureKind::Invalid],
        'bad entry after a good one' => [['schedule' => [$entry(), $entry(1, '09:00', 'noon')]], 'invalid_time_of_day', DomainFailureKind::Invalid],
        'start after end' => [['schedule' => [$entry(1, '18:00', '09:00')]], 'schedule_interval_inverted', DomainFailureKind::Invalid],
        'start equal to end' => [['schedule' => [$entry(1, '09:00', '09:00')]], 'schedule_interval_inverted', DomainFailureKind::Invalid],
        'overlapping intervals' => [['schedule' => [$entry(1, '09:00', '15:00'), $entry(1, '14:00', '18:00')]], 'overlapping_schedule_intervals', DomainFailureKind::Conflict],
        'one interval inside another' => [['schedule' => [$entry(4, '08:00', '20:00'), $entry(4, '12:00', '13:00')]], 'overlapping_schedule_intervals', DomainFailureKind::Conflict],
        'the same interval twice' => [['schedule' => [$entry(5), $entry(5)]], 'overlapping_schedule_intervals', DomainFailureKind::Conflict],
    ];
});
