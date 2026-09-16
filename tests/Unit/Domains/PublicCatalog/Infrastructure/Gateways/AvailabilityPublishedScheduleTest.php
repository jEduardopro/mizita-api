<?php

declare(strict_types=1);

use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Domains\PublicCatalog\Infrastructure\Gateways\AvailabilityPublishedSchedule;
use App\Domains\PublicCatalog\ValueObjects\PublicScheduleEntry;
use Tests\Support\Availability\FakeScheduleRuleRepository;
use Tests\Support\Availability\ScheduleFixtures;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->rules = new FakeScheduleRuleRepository;

    $this->gateway = new AvailabilityPublishedSchedule($this->rules);

    $this->publish = fn (...$rules) => $this->rules->store(
        ScheduleOwnerType::Business,
        PublicCatalogFixtures::BUSINESS_ID,
        ...$rules,
    );

    $this->read = fn (string $businessId = PublicCatalogFixtures::BUSINESS_ID): array => $this->gateway
        ->forBusiness($businessId);
});

describe('the opening hours a visitor reads', function () {
    it('translates each rule into a weekday and two local times', function () {
        ($this->publish)(ScheduleFixtures::rule(
            businessId: PublicCatalogFixtures::BUSINESS_ID,
            ownerId: PublicCatalogFixtures::BUSINESS_ID,
            weekday: Weekday::Monday,
            startsAt: '09:00',
            endsAt: '14:00',
        ));

        $schedule = ($this->read)();

        expect($schedule)->toHaveCount(1)
            ->and($schedule[0])->toBeInstanceOf(PublicScheduleEntry::class)
            ->and($schedule[0]->weekday)->toBe(Weekday::Monday->value)
            ->and($schedule[0]->startsAt)->toBe('09:00')
            ->and($schedule[0]->endsAt)->toBe('14:00');
    });

    it('publishes the hours as local wall clock strings, never as instants', function () {
        ($this->publish)(ScheduleFixtures::rule(
            businessId: PublicCatalogFixtures::BUSINESS_ID,
            ownerId: PublicCatalogFixtures::BUSINESS_ID,
            weekday: Weekday::Sunday,
            startsAt: '09:05',
            endsAt: '23:59',
        ));

        $entry = ($this->read)()[0];

        expect($entry->startsAt)->toBe('09:05')
            ->and($entry->endsAt)->toBe('23:59')
            ->and($entry->startsAt)->not->toContain('T')
            ->and($entry->weekday)->toBe(7);
    });

    it('keeps every interval of a split day, in the order the repository returned them', function () {
        ($this->publish)(
            ScheduleFixtures::rule(
                businessId: PublicCatalogFixtures::BUSINESS_ID,
                ownerId: PublicCatalogFixtures::BUSINESS_ID,
                startsAt: ScheduleFixtures::MORNING_START,
                endsAt: ScheduleFixtures::MORNING_END,
            ),
            ScheduleFixtures::rule(
                id: ScheduleFixtures::SECOND_RULE_ID,
                businessId: PublicCatalogFixtures::BUSINESS_ID,
                ownerId: PublicCatalogFixtures::BUSINESS_ID,
                startsAt: ScheduleFixtures::AFTERNOON_START,
                endsAt: ScheduleFixtures::AFTERNOON_END,
            ),
        );

        expect(array_column(($this->read)(), 'startsAt'))
            ->toBe([ScheduleFixtures::MORNING_START, ScheduleFixtures::AFTERNOON_START]);
    });

    it('asks for the hours of the business itself, not of a staff member', function () {
        ($this->read)();

        expect($this->rules->reads)->toBe([[
            'ownerType' => ScheduleOwnerType::Business,
            'ownerId' => PublicCatalogFixtures::BUSINESS_ID,
        ]]);
    });

    it('never publishes the hours a staff member keeps to themselves', function () {
        $this->rules->store(
            ScheduleOwnerType::StaffMember,
            ScheduleFixtures::STAFF_ID,
            ScheduleFixtures::rule(
                businessId: PublicCatalogFixtures::BUSINESS_ID,
                ownerType: ScheduleOwnerType::StaffMember,
                ownerId: ScheduleFixtures::STAFF_ID,
                startsAt: ScheduleFixtures::AFTERNOON_START,
                endsAt: ScheduleFixtures::AFTERNOON_END,
            ),
        );

        expect(($this->read)())->toBe([]);
    });

    it('answers with an empty week for a business that published no hours', function () {
        expect(($this->read)())->toBe([]);
    });

    it('publishes no rule identity, because a visitor reads hours and not rows', function () {
        $fields = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(PublicScheduleEntry::class))->getProperties(),
        );

        expect($fields)->toBe(['weekday', 'startsAt', 'endsAt']);
    });
});
