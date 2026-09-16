<?php

declare(strict_types=1);

use App\Domains\Availability\Entities\ScheduleRule;
use App\Domains\Availability\Infrastructure\Eloquent\Mappers\ScheduleRuleMapper;
use App\Domains\Availability\Infrastructure\Eloquent\Models\ScheduleRuleModel;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\Weekday;
use Tests\Support\Availability\ScheduleFixtures;
use Tests\Support\FakeBusinessContext;

const SCHEDULE_RULE_BUSINESS_KEY = 42;

const SCHEDULE_RULE_OWNER_KEY = 7;

/**
 * @param  array<string, mixed>  $overrides
 */
function scheduleRuleRow(array $overrides = []): ScheduleRuleModel
{
    $model = new ScheduleRuleModel;

    $model->setRawAttributes([
        'id' => 13,
        'uuid' => ScheduleFixtures::RULE_ID,
        'business_id' => SCHEDULE_RULE_BUSINESS_KEY,
        'owner_type' => 'business',
        'owner_id' => SCHEDULE_RULE_OWNER_KEY,
        'weekday' => 1,
        'starts_at' => '09:00:00',
        'ends_at' => '14:00:00',
        'created_at' => ScheduleFixtures::now(),
        ...$overrides,
    ], true);

    return $model;
}

beforeEach(function () {
    $this->mapper = new ScheduleRuleMapper;
});

describe('reading a row', function () {
    it('restores every fact the row carries', function () {
        $rule = $this->mapper->toEntity(
            scheduleRuleRow(),
            FakeBusinessContext::BUSINESS_ID,
            FakeBusinessContext::BUSINESS_ID,
        );

        expect($rule)->toBeInstanceOf(ScheduleRule::class)
            ->and($rule->id)->toBe(ScheduleFixtures::RULE_ID)
            ->and($rule->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($rule->ownerType)->toBe(ScheduleOwnerType::Business)
            ->and($rule->ownerId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($rule->weekday)->toBe(Weekday::Monday)
            ->and($rule->startsAt()->toString())->toBe('09:00')
            ->and($rule->endsAt()->toString())->toBe('14:00')
            ->and($rule->createdAt)->toEqual(ScheduleFixtures::now());
    });

    it('takes the identity from the uuid column, not from the primary key', function () {
        expect($this->mapper->toEntity(
            scheduleRuleRow(),
            FakeBusinessContext::BUSINESS_ID,
            FakeBusinessContext::BUSINESS_ID,
        )->id)->toBe(ScheduleFixtures::RULE_ID);
    });

    it('reads the owner back as the uuid it was handed, never as the column', function () {
        $rule = $this->mapper->toEntity(
            scheduleRuleRow(),
            FakeBusinessContext::BUSINESS_ID,
            ScheduleFixtures::STAFF_ID,
        );

        expect($rule->ownerId)->toBe(ScheduleFixtures::STAFF_ID)
            ->and($rule->ownerId)->not->toBe((string) SCHEDULE_RULE_OWNER_KEY);
    });

    it('reads the business back as the uuid it was handed, never as the column', function () {
        $rule = $this->mapper->toEntity(
            scheduleRuleRow(),
            FakeBusinessContext::BUSINESS_ID,
            FakeBusinessContext::BUSINESS_ID,
        );

        expect($rule->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($rule->businessId)->not->toBe((string) SCHEDULE_RULE_BUSINESS_KEY);
    });

    it('drops the seconds a time column hands back', function () {
        $rule = $this->mapper->toEntity(
            scheduleRuleRow(['starts_at' => '08:30:00', 'ends_at' => '12:45:00']),
            FakeBusinessContext::BUSINESS_ID,
            FakeBusinessContext::BUSINESS_ID,
        );

        expect($rule->startsAt()->toString())->toBe('08:30')
            ->and($rule->endsAt()->toString())->toBe('12:45');
    });

    it('reads the owner kind back from the alias the row stores', function (string $alias, ScheduleOwnerType $type) {
        expect($this->mapper->toEntity(
            scheduleRuleRow(['owner_type' => $alias]),
            FakeBusinessContext::BUSINESS_ID,
            FakeBusinessContext::BUSINESS_ID,
        )->ownerType)->toBe($type);
    })->with([
        'business' => ['business', ScheduleOwnerType::Business],
        'staff member' => ['staff_member', ScheduleOwnerType::StaffMember],
    ]);

    it('reads every weekday back from its ISO number', function (int $number, Weekday $weekday) {
        expect($this->mapper->toEntity(
            scheduleRuleRow(['weekday' => $number]),
            FakeBusinessContext::BUSINESS_ID,
            FakeBusinessContext::BUSINESS_ID,
        )->weekday)->toBe($weekday);
    })->with([
        [1, Weekday::Monday],
        [6, Weekday::Saturday],
        [7, Weekday::Sunday],
    ]);

    it('restores an interval a rule could never have been created with', function () {
        $rule = $this->mapper->toEntity(
            scheduleRuleRow(['starts_at' => '18:00:00', 'ends_at' => '09:00:00']),
            FakeBusinessContext::BUSINESS_ID,
            FakeBusinessContext::BUSINESS_ID,
        );

        expect($rule->startsAt()->toString())->toBe('18:00')
            ->and($rule->endsAt()->toString())->toBe('09:00');
    });
});

describe('writing a row', function () {
    it('spreads the rule across its seven columns', function () {
        expect($this->mapper->toAttributes(
            ScheduleFixtures::rule(),
            SCHEDULE_RULE_BUSINESS_KEY,
            SCHEDULE_RULE_OWNER_KEY,
        ))->toBe([
            'uuid' => ScheduleFixtures::RULE_ID,
            'business_id' => SCHEDULE_RULE_BUSINESS_KEY,
            'owner_type' => 'business',
            'owner_id' => SCHEDULE_RULE_OWNER_KEY,
            'weekday' => 1,
            'starts_at' => '09:00',
            'ends_at' => '14:00',
        ]);
    });

    it('writes the business and the owner as the int keys it was handed', function () {
        $attributes = $this->mapper->toAttributes(
            ScheduleFixtures::rule(ownerType: ScheduleOwnerType::StaffMember, ownerId: ScheduleFixtures::STAFF_ID),
            SCHEDULE_RULE_BUSINESS_KEY,
            SCHEDULE_RULE_OWNER_KEY,
        );

        expect($attributes['business_id'])->toBeInt()
            ->and($attributes['owner_id'])->toBeInt()
            ->and($attributes)->not->toContain(ScheduleFixtures::STAFF_ID)
            ->and($attributes)->not->toContain(FakeBusinessContext::BUSINESS_ID);
    });

    it('writes the owner kind as the stored alias', function (ScheduleOwnerType $type, string $alias) {
        expect($this->mapper->toAttributes(
            ScheduleFixtures::rule(ownerType: $type, ownerId: ScheduleFixtures::STAFF_ID),
            SCHEDULE_RULE_BUSINESS_KEY,
            SCHEDULE_RULE_OWNER_KEY,
        )['owner_type'])->toBe($alias);
    })->with([
        'business' => [ScheduleOwnerType::Business, 'business'],
        'staff member' => [ScheduleOwnerType::StaffMember, 'staff_member'],
    ]);

    it('writes the weekday as the ISO number, not as a name', function () {
        expect($this->mapper->toAttributes(
            ScheduleFixtures::rule(weekday: Weekday::Sunday),
            SCHEDULE_RULE_BUSINESS_KEY,
            SCHEDULE_RULE_OWNER_KEY,
        )['weekday'])->toBe(7);
    });

    it('writes the times as local clock faces with no zone attached', function () {
        $attributes = $this->mapper->toAttributes(
            ScheduleFixtures::rule(startsAt: '09:05', endsAt: '17:30'),
            SCHEDULE_RULE_BUSINESS_KEY,
            SCHEDULE_RULE_OWNER_KEY,
        );

        expect($attributes['starts_at'])->toBe('09:05')
            ->and($attributes['ends_at'])->toBe('17:30');
    });

    it('never writes the internal primary key or the creation instant', function () {
        $attributes = $this->mapper->toAttributes(
            ScheduleFixtures::rule(),
            SCHEDULE_RULE_BUSINESS_KEY,
            SCHEDULE_RULE_OWNER_KEY,
        );

        expect($attributes)->not->toHaveKey('id')
            ->and($attributes)->not->toHaveKey('created_at');
    });
});

it('survives a full round trip without losing a fact', function () {
    $rule = ScheduleFixtures::rule(
        ownerType: ScheduleOwnerType::StaffMember,
        ownerId: ScheduleFixtures::STAFF_ID,
        weekday: Weekday::Saturday,
        startsAt: '08:15',
        endsAt: '19:45',
    );

    $attributes = $this->mapper->toAttributes($rule, SCHEDULE_RULE_BUSINESS_KEY, SCHEDULE_RULE_OWNER_KEY);

    $restored = $this->mapper->toEntity(
        scheduleRuleRow([...$attributes, 'created_at' => ScheduleFixtures::now()]),
        FakeBusinessContext::BUSINESS_ID,
        ScheduleFixtures::STAFF_ID,
    );

    expect($restored->id)->toBe($rule->id)
        ->and($restored->businessId)->toBe($rule->businessId)
        ->and($restored->ownerType)->toBe($rule->ownerType)
        ->and($restored->ownerId)->toBe($rule->ownerId)
        ->and($restored->weekday)->toBe($rule->weekday)
        ->and($restored->startsAt()->toString())->toBe($rule->startsAt()->toString())
        ->and($restored->endsAt()->toString())->toBe($rule->endsAt()->toString())
        ->and($restored->createdAt)->toEqual($rule->createdAt);
});
