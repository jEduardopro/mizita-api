<?php

declare(strict_types=1);

use App\Domains\Availability\Application\Dtos\ApplyDefaultHoursInput;
use App\Domains\Availability\Application\Dtos\ScheduleRuleData;
use App\Domains\Availability\Application\UseCases\ApplyDefaultHours;
use App\Domains\Availability\Contracts\ScheduleRuleRepository;
use App\Domains\Availability\Entities\ScheduleRule;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Exceptions\UseCaseFailed;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Availability\FakeScheduleRuleRepository;
use Tests\Support\Availability\ScheduleFixtures;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;

beforeEach(function () {
    $this->businessId = ScheduleFixtures::OTHER_BUSINESS_ID;

    $this->ruleIds = [
        '01930000-0000-7000-8000-000000000101',
        '01930000-0000-7000-8000-000000000102',
        '01930000-0000-7000-8000-000000000103',
        '01930000-0000-7000-8000-000000000104',
        '01930000-0000-7000-8000-000000000105',
    ];
    $this->spareId = '01930000-0000-7000-8000-000000000106';

    $this->rules = new FakeScheduleRuleRepository;
    $this->ids = new FixedIdGenerator(...[...$this->ruleIds, $this->spareId]);

    $this->useCaseOver = fn (ScheduleRuleRepository $rules, ?Clock $clock = null): ApplyDefaultHours => new ApplyDefaultHours(
        $rules,
        $this->ids,
        $clock ?? new FakeClock(ScheduleFixtures::now()),
    );

    $this->apply = fn (
        ScheduleOwnerType $ownerType = ScheduleOwnerType::Business,
        ?string $ownerId = null,
        ?ScheduleRuleRepository $rules = null,
        ?Clock $clock = null,
    ) => ($this->useCaseOver)($rules ?? $this->rules, $clock)->handle(new ApplyDefaultHoursInput(
        businessId: $this->businessId,
        ownerType: $ownerType,
        ownerId: $ownerId ?? $this->businessId,
    ));
});

describe('applying the default week to an owner with no hours', function () {
    it('answers with Monday to Friday, 09:00 to 18:00, one entry per day', function () {
        $data = ($this->apply)()->value();

        expect($data)->toHaveCount(5)
            ->and($data)->each->toBeInstanceOf(ScheduleRuleData::class)
            ->and(array_column($data, 'weekday'))->toBe([1, 2, 3, 4, 5])
            ->and(array_column($data, 'startsAt'))->toBe(array_fill(0, 5, '09:00'))
            ->and(array_column($data, 'endsAt'))->toBe(array_fill(0, 5, '18:00'));
    });

    it('hands back the uuids the identity generated, one per rule and in order', function () {
        $ids = array_column(($this->apply)()->value(), 'id');

        expect($ids)->toBe($this->ruleIds)
            ->and(array_filter($ids, is_numeric(...)))->toBe([]);
    });

    it('draws exactly one identity per rule and no more', function () {
        ($this->apply)();

        expect($this->ids->next())->toBe($this->spareId);
    });

    it('writes the whole default week in one replacement', function () {
        $data = ($this->apply)()->value();

        expect($this->rules->replacements)->toHaveCount(1)
            ->and(array_map(static fn (ScheduleRule $rule): string => $rule->id, $this->rules->lastReplacement()))
            ->toBe(array_column($data, 'id'));
    });

    it('builds each rule from the default interval of its weekday', function () {
        ($this->apply)();

        $rules = $this->rules->lastReplacement();

        expect(array_map(static fn (ScheduleRule $rule): Weekday => $rule->weekday, $rules))->toBe([
            Weekday::Monday,
            Weekday::Tuesday,
            Weekday::Wednesday,
            Weekday::Thursday,
            Weekday::Friday,
        ]);

        foreach ($rules as $rule) {
            expect($rule->startsAt()->toString())->toBe('09:00')
                ->and($rule->endsAt()->toString())->toBe('18:00');
        }
    });

    it('stamps every rule with the instant the clock reported', function () {
        ($this->apply)();

        foreach ($this->rules->lastReplacement() as $rule) {
            expect($rule->createdAt)->toEqual(ScheduleFixtures::now());
        }
    });

    it('reads the clock once and shares that instant across the whole week', function () {
        $clock = Mockery::mock(Clock::class);
        $clock->shouldReceive('now')->once()->andReturn(ScheduleFixtures::now());

        ($this->apply)(clock: $clock);

        $stamps = array_map(static fn (ScheduleRule $rule): DateTimeImmutable => $rule->createdAt, $this->rules->lastReplacement());

        expect($stamps)->toHaveCount(5)
            ->and(array_unique(array_map(spl_object_id(...), $stamps)))->toHaveCount(1);
    });
});

describe('the owner the default hours belong to', function () {
    it('files the rules under the owner it was asked about', function (ScheduleOwnerType $ownerType, string $ownerId) {
        ($this->apply)($ownerType, $ownerId);

        $replacement = $this->rules->replacements[0];

        expect($replacement['ownerType'])->toBe($ownerType)
            ->and($replacement['ownerId'])->toBe($ownerId)
            ->and($replacement['ownerId'])->toBeString();

        foreach ($replacement['rules'] as $rule) {
            expect($rule->ownerType)->toBe($ownerType)
                ->and($rule->ownerId)->toBe($ownerId);
        }
    })->with([
        'the business itself' => [ScheduleOwnerType::Business, ScheduleFixtures::OTHER_BUSINESS_ID],
        'a staff member' => [ScheduleOwnerType::StaffMember, ScheduleFixtures::STAFF_ID],
    ]);

    it('asks the repository about that owner alone before writing anything', function () {
        ($this->apply)(ScheduleOwnerType::StaffMember, ScheduleFixtures::STAFF_ID);

        expect($this->rules->reads)->toBe([[
            'ownerType' => ScheduleOwnerType::StaffMember,
            'ownerId' => ScheduleFixtures::STAFF_ID,
        ]]);
    });
});

describe('the business the default hours belong to', function () {
    it('scopes every rule to the business the input names', function () {
        ($this->apply)(ScheduleOwnerType::StaffMember, ScheduleFixtures::STAFF_ID);

        foreach ($this->rules->lastReplacement() as $rule) {
            expect($rule->businessId)->toBe(ScheduleFixtures::OTHER_BUSINESS_ID)
                ->and(is_numeric($rule->businessId))->toBeFalse();
        }
    });

    it('tells the repository which business to replace the hours in', function () {
        ($this->apply)(ScheduleOwnerType::StaffMember, ScheduleFixtures::STAFF_ID);

        expect($this->rules->replacements[0]['businessId'])->toBe(ScheduleFixtures::OTHER_BUSINESS_ID);
    });

    it('depends on no business context, so it runs before any request has bound one', function () {
        $dependencies = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionMethod(ApplyDefaultHours::class, '__construct'))->getParameters(),
        );

        expect($dependencies)->toBe([ScheduleRuleRepository::class, IdGenerator::class, Clock::class])
            ->and($dependencies)->not->toContain(BusinessContext::class);
    });
});

describe('an owner who already has hours', function () {
    beforeEach(function () {
        $this->existing = [
            ScheduleFixtures::rule(
                id: ScheduleFixtures::RULE_ID,
                businessId: ScheduleFixtures::OTHER_BUSINESS_ID,
                ownerType: ScheduleOwnerType::StaffMember,
                ownerId: ScheduleFixtures::STAFF_ID,
                weekday: Weekday::Saturday,
                startsAt: '10:00',
                endsAt: '14:00',
            ),
            ScheduleFixtures::rule(
                id: ScheduleFixtures::SECOND_RULE_ID,
                businessId: ScheduleFixtures::OTHER_BUSINESS_ID,
                ownerType: ScheduleOwnerType::StaffMember,
                ownerId: ScheduleFixtures::STAFF_ID,
                weekday: Weekday::Sunday,
                startsAt: '11:00',
                endsAt: '15:30',
            ),
        ];

        $this->rules->store(ScheduleOwnerType::StaffMember, ScheduleFixtures::STAFF_ID, ...$this->existing);
    });

    it('answers with the hours on file instead of the defaults', function () {
        $data = ($this->apply)(ScheduleOwnerType::StaffMember, ScheduleFixtures::STAFF_ID)->value();

        expect($data)->toHaveCount(2)
            ->and($data[0])->toBeInstanceOf(ScheduleRuleData::class)
            ->and($data[0]->id)->toBe(ScheduleFixtures::RULE_ID)
            ->and($data[0]->weekday)->toBe(6)
            ->and($data[0]->startsAt)->toBe('10:00')
            ->and($data[0]->endsAt)->toBe('14:00')
            ->and($data[1]->id)->toBe(ScheduleFixtures::SECOND_RULE_ID)
            ->and($data[1]->weekday)->toBe(7)
            ->and($data[1]->startsAt)->toBe('11:00')
            ->and($data[1]->endsAt)->toBe('15:30');
    });

    it('writes nothing, so applying the defaults twice is harmless', function () {
        ($this->apply)(ScheduleOwnerType::StaffMember, ScheduleFixtures::STAFF_ID);

        expect($this->rules->replacements)->toBe([]);
    });

    it('draws no identity and never reads the clock', function () {
        $clock = Mockery::mock(Clock::class);
        $clock->shouldNotReceive('now');

        ($this->apply)(ScheduleOwnerType::StaffMember, ScheduleFixtures::STAFF_ID, clock: $clock);

        expect($this->ids->next())->toBe($this->ruleIds[0]);
    });

    it('still gives defaults to a different owner of the same kind', function () {
        ($this->apply)(ScheduleOwnerType::StaffMember, ScheduleFixtures::OTHER_STAFF_ID);

        expect($this->rules->replacements)->toHaveCount(1)
            ->and($this->rules->replacements[0]['ownerId'])->toBe(ScheduleFixtures::OTHER_STAFF_ID)
            ->and($this->rules->lastReplacement())->toHaveCount(5);
    });

    it('still gives defaults to the business when only its staff member has hours', function () {
        ($this->apply)(ScheduleOwnerType::Business, ScheduleFixtures::OTHER_BUSINESS_ID);

        expect($this->rules->replacements)->toHaveCount(1)
            ->and($this->rules->replacements[0]['ownerType'])->toBe(ScheduleOwnerType::Business)
            ->and($this->rules->lastReplacement())->toHaveCount(5);
    });
});

describe('a repository that refuses', function () {
    it('returns the refusal of the write instead of throwing it at the caller', function (DomainFailure&Throwable $failure, string $code, DomainFailureKind $kind) {
        $rules = Mockery::mock(ScheduleRuleRepository::class);
        $rules->shouldReceive('allForOwner')->once()->andReturn([]);
        $rules->shouldReceive('replaceForOwner')->once()->andThrow($failure);

        $response = ($this->apply)(rules: $rules);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe($kind)
            ->and($response->error()->cause())->toBe($failure);
    })->with([
        'the business is unknown' => fn () => [
            BusinessNotFound::withId(ScheduleFixtures::OTHER_BUSINESS_ID),
            'business_not_found',
            DomainFailureKind::NotFound,
        ],
        'any other domain failure' => fn () => [
            UseCaseFailed::with('schedule_write_refused', DomainFailureKind::Conflict),
            'schedule_write_refused',
            DomainFailureKind::Conflict,
        ],
    ]);

    it('returns the refusal of the read and writes nothing', function () {
        $failure = UseCaseFailed::with('schedule_read_refused', DomainFailureKind::Forbidden);

        $rules = Mockery::mock(ScheduleRuleRepository::class);
        $rules->shouldReceive('allForOwner')->once()->andThrow($failure);
        $rules->shouldNotReceive('replaceForOwner');

        $response = ($this->apply)(rules: $rules);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('schedule_read_refused')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden);
    });

    it('lets an infrastructure error out untouched, since it is no refusal', function () {
        $bug = new RuntimeException('the schedule_rules table is gone');

        $rules = Mockery::mock(ScheduleRuleRepository::class);
        $rules->shouldReceive('allForOwner')->once()->andReturn([]);
        $rules->shouldReceive('replaceForOwner')->once()->andThrow($bug);

        expect(fn () => ($this->apply)(rules: $rules))->toThrow($bug);
    });
});
