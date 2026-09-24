<?php

declare(strict_types=1);

use App\Domains\Availability\Application\Dtos\MyScheduleData;
use App\Domains\Availability\Application\Dtos\ScheduleRuleData;
use App\Domains\Availability\Application\Dtos\ShowMyScheduleInput;
use App\Domains\Availability\Application\UseCases\ShowMySchedule;
use App\Domains\Availability\Exceptions\StaffMembershipNotFound;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Availability\FakeScheduleRuleRepository;
use Tests\Support\Availability\FakeStaffMembership;
use Tests\Support\Availability\ScheduleFixtures;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->accountId = '01930000-0000-7000-8000-0000000000a1';
    $this->strangerAccountId = '01930000-0000-7000-8000-0000000000a9';

    $this->memberships = (new FakeStaffMembership)
        ->grant(FakeBusinessContext::BUSINESS_ID, $this->accountId, ScheduleFixtures::STAFF_ID);
    $this->rules = new FakeScheduleRuleRepository;

    $this->businessRules = [
        ScheduleFixtures::rule(
            id: ScheduleFixtures::RULE_ID,
            weekday: Weekday::Monday,
            startsAt: '09:00',
            endsAt: '18:00',
        ),
        ScheduleFixtures::rule(
            id: ScheduleFixtures::SECOND_RULE_ID,
            weekday: Weekday::Saturday,
            startsAt: '10:00',
            endsAt: '14:00',
        ),
    ];
    $this->rules->store(ScheduleOwnerType::Business, FakeBusinessContext::BUSINESS_ID, ...$this->businessRules);

    $this->staffRule = fn (string $id, Weekday $weekday, string $startsAt, string $endsAt, string $staffId = ScheduleFixtures::STAFF_ID) => ScheduleFixtures::rule(
        id: $id,
        ownerType: ScheduleOwnerType::StaffMember,
        ownerId: $staffId,
        weekday: $weekday,
        startsAt: $startsAt,
        endsAt: $endsAt,
    );

    $this->show = fn (?string $accountId = null, ?FakeBusinessContext $business = null) => (new ShowMySchedule(
        $this->memberships,
        $this->rules,
        $business ?? new FakeBusinessContext,
    ))->handle(new ShowMyScheduleInput($accountId ?? $this->accountId));
});

describe('a staff member with hours of their own', function () {
    beforeEach(function () {
        $this->rules->store(
            ScheduleOwnerType::StaffMember,
            ScheduleFixtures::STAFF_ID,
            ($this->staffRule)(ScheduleFixtures::THIRD_RULE_ID, Weekday::Tuesday, '07:30', '12:15'),
            ($this->staffRule)(ScheduleFixtures::GENERATED_RULE_ID, Weekday::Sunday, '16:00', '22:00'),
        );
    });

    it('answers with their own rules, entry by entry', function () {
        $data = ($this->show)()->value();

        expect($data)->toBeInstanceOf(MyScheduleData::class)
            ->and($data->schedule)->toHaveCount(2)
            ->and($data->schedule)->each->toBeInstanceOf(ScheduleRuleData::class)
            ->and($data->schedule[0]->id)->toBe(ScheduleFixtures::THIRD_RULE_ID)
            ->and($data->schedule[0]->weekday)->toBe(2)
            ->and($data->schedule[0]->startsAt)->toBe('07:30')
            ->and($data->schedule[0]->endsAt)->toBe('12:15')
            ->and($data->schedule[1]->id)->toBe(ScheduleFixtures::GENERATED_RULE_ID)
            ->and($data->schedule[1]->weekday)->toBe(7)
            ->and($data->schedule[1]->startsAt)->toBe('16:00')
            ->and($data->schedule[1]->endsAt)->toBe('22:00');
    });

    it('flags the schedule as their own rather than inherited', function () {
        expect(($this->show)()->value()->inherited)->toBeFalse();
    });

    it('identifies every rule by its uuid, never by a row number', function () {
        $ids = array_column(($this->show)()->value()->schedule, 'id');

        expect($ids)->toBe([ScheduleFixtures::THIRD_RULE_ID, ScheduleFixtures::GENERATED_RULE_ID])
            ->and(array_filter($ids, is_numeric(...)))->toBe([]);
    });

    it('never reads the business hours when their own are enough', function () {
        ($this->show)();

        expect($this->rules->reads)->toBe([[
            'ownerType' => ScheduleOwnerType::StaffMember,
            'ownerId' => ScheduleFixtures::STAFF_ID,
        ]]);
    });
});

describe('a staff member with no hours of their own', function () {
    it('answers with the business hours, entry by entry', function () {
        $data = ($this->show)()->value();

        expect($data->schedule)->toHaveCount(2)
            ->and($data->schedule[0]->id)->toBe(ScheduleFixtures::RULE_ID)
            ->and($data->schedule[0]->weekday)->toBe(1)
            ->and($data->schedule[0]->startsAt)->toBe('09:00')
            ->and($data->schedule[0]->endsAt)->toBe('18:00')
            ->and($data->schedule[1]->id)->toBe(ScheduleFixtures::SECOND_RULE_ID)
            ->and($data->schedule[1]->weekday)->toBe(6)
            ->and($data->schedule[1]->startsAt)->toBe('10:00')
            ->and($data->schedule[1]->endsAt)->toBe('14:00');
    });

    it('flags the schedule as inherited from the business', function () {
        expect(($this->show)()->value()->inherited)->toBeTrue();
    });

    it('looks for their own hours first, then falls back to the business', function () {
        ($this->show)();

        expect($this->rules->reads)->toBe([
            ['ownerType' => ScheduleOwnerType::StaffMember, 'ownerId' => ScheduleFixtures::STAFF_ID],
            ['ownerType' => ScheduleOwnerType::Business, 'ownerId' => FakeBusinessContext::BUSINESS_ID],
        ]);
    });

    it('does not borrow the hours of a colleague', function () {
        $this->rules->store(
            ScheduleOwnerType::StaffMember,
            ScheduleFixtures::OTHER_STAFF_ID,
            ($this->staffRule)(ScheduleFixtures::THIRD_RULE_ID, Weekday::Friday, '06:00', '10:00', ScheduleFixtures::OTHER_STAFF_ID),
        );

        $data = ($this->show)()->value();

        expect($data->inherited)->toBeTrue()
            ->and(array_column($data->schedule, 'id'))->toBe([ScheduleFixtures::RULE_ID, ScheduleFixtures::SECOND_RULE_ID]);
    });

    it('answers with an empty inherited week when the business has no hours either', function () {
        $this->memberships->grant(ScheduleFixtures::OTHER_BUSINESS_ID, $this->accountId, ScheduleFixtures::STAFF_ID);

        $data = ($this->show)(business: new FakeBusinessContext(ScheduleFixtures::OTHER_BUSINESS_ID))->value();

        expect($data->inherited)->toBeTrue()
            ->and($data->schedule)->toBe([]);
    });
});

describe('the business the schedule is read in', function () {
    beforeEach(function () {
        $this->memberships->grant(ScheduleFixtures::OTHER_BUSINESS_ID, $this->accountId, ScheduleFixtures::OTHER_STAFF_ID);
        $this->rules->store(
            ScheduleOwnerType::Business,
            ScheduleFixtures::OTHER_BUSINESS_ID,
            ScheduleFixtures::rule(
                id: ScheduleFixtures::GENERATED_RULE_ID,
                businessId: ScheduleFixtures::OTHER_BUSINESS_ID,
                ownerId: ScheduleFixtures::OTHER_BUSINESS_ID,
                weekday: Weekday::Wednesday,
            ),
        );
    });

    it('resolves the membership inside the business the caller is operating', function () {
        ($this->show)(business: new FakeBusinessContext(ScheduleFixtures::OTHER_BUSINESS_ID));

        expect($this->memberships->lookups)->toBe([[
            'businessId' => ScheduleFixtures::OTHER_BUSINESS_ID,
            'accountId' => $this->accountId,
        ]]);
    });

    it('reads the staff member that membership resolves to', function () {
        ($this->show)(business: new FakeBusinessContext(ScheduleFixtures::OTHER_BUSINESS_ID));

        expect($this->rules->reads[0])->toBe([
            'ownerType' => ScheduleOwnerType::StaffMember,
            'ownerId' => ScheduleFixtures::OTHER_STAFF_ID,
        ]);
    });

    it('inherits the hours of the business in context and of no other', function () {
        $data = ($this->show)(business: new FakeBusinessContext(ScheduleFixtures::OTHER_BUSINESS_ID))->value();

        expect($data->inherited)->toBeTrue()
            ->and(array_column($data->schedule, 'id'))->toBe([ScheduleFixtures::GENERATED_RULE_ID])
            ->and($this->rules->reads[1])->toBe([
                'ownerType' => ScheduleOwnerType::Business,
                'ownerId' => ScheduleFixtures::OTHER_BUSINESS_ID,
            ]);
    });
});

describe('a caller who is not a staff member of the business', function () {
    it('refuses an account with no membership at all', function () {
        $response = ($this->show)(accountId: $this->strangerAccountId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_accessible')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden)
            ->and($response->error()->cause())->toBeInstanceOf(StaffMembershipNotFound::class);
    });

    it('refuses an account whose membership belongs to another business', function () {
        $this->memberships->grant(ScheduleFixtures::OTHER_BUSINESS_ID, $this->strangerAccountId, ScheduleFixtures::OTHER_STAFF_ID);

        $response = ($this->show)(accountId: $this->strangerAccountId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_accessible');
    });

    it('reads no hours at all for a caller it refused', function () {
        ($this->show)(accountId: $this->strangerAccountId);

        expect($this->rules->reads)->toBe([]);
    });
});
