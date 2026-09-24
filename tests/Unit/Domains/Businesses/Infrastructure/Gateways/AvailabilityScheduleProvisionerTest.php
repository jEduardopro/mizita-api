<?php

declare(strict_types=1);

use App\Domains\Availability\Application\UseCases\ApplyDefaultHours;
use App\Domains\Availability\Contracts\ScheduleRuleRepository;
use App\Domains\Availability\Entities\ScheduleRule;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Domains\Businesses\Contracts\ScheduleProvisioner;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\Infrastructure\Gateways\AvailabilityScheduleProvisioner;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Exceptions\UseCaseFailed;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Availability\FakeScheduleRuleRepository;
use Tests\Support\Availability\ScheduleFixtures;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;

beforeEach(function () {
    $this->businessId = OnboardingFixtures::GENERATED_BUSINESS_ID;
    $this->ownerStaffMemberId = ScheduleFixtures::STAFF_ID;

    $this->ruleIds = array_map(
        static fn (int $sequence): string => sprintf('01930000-0000-7000-8000-0000000002%02d', $sequence),
        range(1, 10),
    );

    $this->rules = new FakeScheduleRuleRepository;

    $this->provisionerOver = fn (ScheduleRuleRepository $rules): AvailabilityScheduleProvisioner => new AvailabilityScheduleProvisioner(
        new ApplyDefaultHours(
            $rules,
            new FixedIdGenerator(...$this->ruleIds),
            new FakeClock(OnboardingFixtures::now()),
        ),
    );

    $this->provisioner = ($this->provisionerOver)($this->rules);

    $this->provision = fn (?AvailabilityScheduleProvisioner $provisioner = null): mixed => ($provisioner ?? $this->provisioner)
        ->provisionDefaultsFor($this->businessId, $this->ownerStaffMemberId);

    $this->weekOf = static fn (array $rules): array => array_map(
        static fn (ScheduleRule $rule): array => [$rule->weekday, $rule->startsAt()->toString(), $rule->endsAt()->toString()],
        $rules,
    );

    $this->defaultWeek = [
        [Weekday::Monday, '09:00', '18:00'],
        [Weekday::Tuesday, '09:00', '18:00'],
        [Weekday::Wednesday, '09:00', '18:00'],
        [Weekday::Thursday, '09:00', '18:00'],
        [Weekday::Friday, '09:00', '18:00'],
    ];
});

describe('provisioning a newly onboarded business', function () {
    it('writes two schedules, the business first and its owner second', function () {
        ($this->provision)();

        expect($this->rules->replacements)->toHaveCount(2)
            ->and(array_column($this->rules->replacements, 'ownerType'))->toBe([
                ScheduleOwnerType::Business,
                ScheduleOwnerType::StaffMember,
            ]);
    });

    it('files the business hours under the business uuid, as business hours', function () {
        ($this->provision)();

        $replacement = $this->rules->replacements[0];

        expect($replacement['businessId'])->toBe($this->businessId)
            ->and($replacement['ownerType'])->toBe(ScheduleOwnerType::Business)
            ->and($replacement['ownerId'])->toBe($this->businessId)
            ->and(($this->weekOf)($replacement['rules']))->toBe($this->defaultWeek);
    });

    it('files the owner hours under the owner staff member, inside the same business', function () {
        ($this->provision)();

        $replacement = $this->rules->replacements[1];

        expect($replacement['businessId'])->toBe($this->businessId)
            ->and($replacement['ownerType'])->toBe(ScheduleOwnerType::StaffMember)
            ->and($replacement['ownerId'])->toBe($this->ownerStaffMemberId)
            ->and(($this->weekOf)($replacement['rules']))->toBe($this->defaultWeek);
    });

    it('scopes every rule to the business uuid, never an internal key', function () {
        ($this->provision)();

        $rules = array_merge(...array_column($this->rules->replacements, 'rules'));

        expect($rules)->toHaveCount(10);

        foreach ($rules as $rule) {
            expect($rule->businessId)->toBe($this->businessId)
                ->and(is_numeric($rule->businessId))->toBeFalse();
        }
    });

    it('gives every rule of both schedules an identity of its own', function () {
        ($this->provision)();

        $ids = array_map(
            static fn (ScheduleRule $rule): string => $rule->id,
            array_merge(...array_column($this->rules->replacements, 'rules')),
        );

        expect($ids)->toBe($this->ruleIds);
    });
});

describe('provisioning a business that already has hours', function () {
    it('leaves the business hours on file and still provisions its owner', function () {
        $this->rules->store(
            ScheduleOwnerType::Business,
            $this->businessId,
            ScheduleFixtures::rule(businessId: $this->businessId, ownerId: $this->businessId, weekday: Weekday::Saturday),
        );

        ($this->provision)();

        expect($this->rules->replacements)->toHaveCount(1)
            ->and($this->rules->replacements[0]['ownerType'])->toBe(ScheduleOwnerType::StaffMember)
            ->and($this->rules->replacements[0]['ownerId'])->toBe($this->ownerStaffMemberId)
            ->and($this->rules->allForOwner(ScheduleOwnerType::Business, $this->businessId))->toHaveCount(1);
    });

    it('writes nothing the second time it runs', function () {
        ($this->provision)();
        ($this->provision)();

        expect($this->rules->replacements)->toHaveCount(2);
    });
});

describe('the rollback contract', function () {
    it('hands nothing back, so no use case response can cross the port', function () {
        expect(($this->provision)())->toBeNull();
    });

    it('declares void on the port', function () {
        expect((string) (new ReflectionMethod(ScheduleProvisioner::class, 'provisionDefaultsFor'))->getReturnType())
            ->toBe('void');
    });

    it('rethrows the refusal itself, so the onboarding transaction rolls back', function (DomainFailure&Throwable $failure) {
        $rules = Mockery::mock(ScheduleRuleRepository::class);
        $rules->shouldReceive('allForOwner')->once()->andReturn([]);
        $rules->shouldReceive('replaceForOwner')->once()->andThrow($failure);

        try {
            ($this->provision)(($this->provisionerOver)($rules));
            $thrown = null;
        } catch (Throwable $escaped) {
            $thrown = $escaped;
        }

        expect($thrown)->toBe($failure);
    })->with([
        'the business is unknown' => fn () => BusinessNotFound::withId(OnboardingFixtures::GENERATED_BUSINESS_ID),
        'any other domain failure' => fn () => UseCaseFailed::with('schedule_write_refused', DomainFailureKind::Conflict),
    ]);

    it('never provisions the owner once the business schedule was refused', function () {
        $rules = Mockery::mock(ScheduleRuleRepository::class);
        $rules->shouldReceive('allForOwner')->once()
            ->with(ScheduleOwnerType::Business, OnboardingFixtures::GENERATED_BUSINESS_ID)
            ->andReturn([]);
        $rules->shouldReceive('replaceForOwner')->once()
            ->andThrow(BusinessNotFound::withId(OnboardingFixtures::GENERATED_BUSINESS_ID));

        expect(fn () => ($this->provision)(($this->provisionerOver)($rules)))->toThrow(BusinessNotFound::class);
    });

    it('lets an infrastructure error out untouched', function () {
        $bug = new RuntimeException('the schedule_rules table is gone');

        $rules = Mockery::mock(ScheduleRuleRepository::class);
        $rules->shouldReceive('allForOwner')->once()->andReturn([]);
        $rules->shouldReceive('replaceForOwner')->once()->andThrow($bug);

        expect(fn () => ($this->provision)(($this->provisionerOver)($rules)))->toThrow($bug);
    });
});
