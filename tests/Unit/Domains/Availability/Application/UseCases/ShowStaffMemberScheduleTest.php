<?php

declare(strict_types=1);

use App\Domains\Availability\Application\Dtos\MyScheduleData;
use App\Domains\Availability\Application\Dtos\ScheduleRuleData;
use App\Domains\Availability\Application\Dtos\ShowStaffMemberScheduleInput;
use App\Domains\Availability\Application\UseCases\ShowStaffMemberSchedule;
use App\Domains\Availability\Exceptions\StaffMemberNotFound;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Availability\FakeScheduleRuleRepository;
use Tests\Support\Availability\FakeStaffRoster;
use Tests\Support\Availability\ScheduleFixtures;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->unknownStaffId = '01930000-0000-7000-8000-0000000000d9';

    $this->roster = (new FakeStaffRoster)
        ->enrol(FakeBusinessContext::BUSINESS_ID, ScheduleFixtures::STAFF_ID)
        ->enrol(ScheduleFixtures::OTHER_BUSINESS_ID, ScheduleFixtures::OTHER_STAFF_ID);
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

    $this->staffRule = fn (
        string $id,
        Weekday $weekday,
        string $startsAt,
        string $endsAt,
        string $staffId = ScheduleFixtures::STAFF_ID,
        string $businessId = FakeBusinessContext::BUSINESS_ID,
    ) => ScheduleFixtures::rule(
        id: $id,
        businessId: $businessId,
        ownerType: ScheduleOwnerType::StaffMember,
        ownerId: $staffId,
        weekday: $weekday,
        startsAt: $startsAt,
        endsAt: $endsAt,
    );

    $this->show = fn (string $staffMemberId = ScheduleFixtures::STAFF_ID, ?FakeBusinessContext $business = null) => (new ShowStaffMemberSchedule(
        $this->roster,
        $this->rules,
        $business ?? new FakeBusinessContext,
    ))->handle(new ShowStaffMemberScheduleInput($staffMemberId));
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
        $colleagueId = '01930000-0000-7000-8000-0000000000d3';
        $this->roster->enrol(FakeBusinessContext::BUSINESS_ID, $colleagueId);
        $this->rules->store(
            ScheduleOwnerType::StaffMember,
            $colleagueId,
            ($this->staffRule)(ScheduleFixtures::THIRD_RULE_ID, Weekday::Friday, '06:00', '10:00', $colleagueId),
        );

        $data = ($this->show)()->value();

        expect($data->inherited)->toBeTrue()
            ->and(array_column($data->schedule, 'id'))->toBe([ScheduleFixtures::RULE_ID, ScheduleFixtures::SECOND_RULE_ID]);
    });

    it('answers with an empty inherited week when the business has no hours either', function () {
        $data = ($this->show)(ScheduleFixtures::OTHER_STAFF_ID, new FakeBusinessContext(ScheduleFixtures::OTHER_BUSINESS_ID))->value();

        expect($data->inherited)->toBeTrue()
            ->and($data->schedule)->toBe([]);
    });
});

describe('the business the schedule is read in', function () {
    beforeEach(function () {
        $this->otherBusiness = new FakeBusinessContext(ScheduleFixtures::OTHER_BUSINESS_ID);
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

    it('confirms the staff member belongs to the business in context', function () {
        ($this->show)(ScheduleFixtures::OTHER_STAFF_ID, $this->otherBusiness);

        expect($this->roster->confirmations)->toBe([[
            'businessId' => ScheduleFixtures::OTHER_BUSINESS_ID,
            'staffMemberId' => ScheduleFixtures::OTHER_STAFF_ID,
        ]]);
    });

    it('reads the hours of the staff member it was asked about', function () {
        ($this->show)(ScheduleFixtures::OTHER_STAFF_ID, $this->otherBusiness);

        expect($this->rules->reads[0])->toBe([
            'ownerType' => ScheduleOwnerType::StaffMember,
            'ownerId' => ScheduleFixtures::OTHER_STAFF_ID,
        ]);
    });

    it('inherits the hours of the business in context and of no other', function () {
        $data = ($this->show)(ScheduleFixtures::OTHER_STAFF_ID, $this->otherBusiness)->value();

        expect($data->inherited)->toBeTrue()
            ->and(array_column($data->schedule, 'id'))->toBe([ScheduleFixtures::GENERATED_RULE_ID])
            ->and($this->rules->reads[1])->toBe([
                'ownerType' => ScheduleOwnerType::Business,
                'ownerId' => ScheduleFixtures::OTHER_BUSINESS_ID,
            ]);
    });
});

describe('a staff member of another business', function () {
    beforeEach(function () {
        $this->rules->store(
            ScheduleOwnerType::StaffMember,
            ScheduleFixtures::OTHER_STAFF_ID,
            ($this->staffRule)(
                ScheduleFixtures::THIRD_RULE_ID,
                Weekday::Thursday,
                '08:00',
                '12:00',
                ScheduleFixtures::OTHER_STAFF_ID,
                ScheduleFixtures::OTHER_BUSINESS_ID,
            ),
        );
    });

    it('reports them as not found rather than forbidden', function () {
        $response = ($this->show)(ScheduleFixtures::OTHER_STAFF_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('staff_member_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($response->error()->cause())->toBeInstanceOf(StaffMemberNotFound::class);
    });

    it('asks the roster about the business in context, not theirs', function () {
        ($this->show)(ScheduleFixtures::OTHER_STAFF_ID);

        expect($this->roster->confirmations)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'staffMemberId' => ScheduleFixtures::OTHER_STAFF_ID,
        ]]);
    });

    it('reads none of their hours', function () {
        ($this->show)(ScheduleFixtures::OTHER_STAFF_ID);

        expect($this->rules->reads)->toBe([]);
    });
});

describe('a staff member it cannot find', function () {
    it('reports an unknown uuid as not found and reads no hours', function () {
        $response = ($this->show)($this->unknownStaffId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('staff_member_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->rules->reads)->toBe([]);
    });

    it('reports an id that is not a uuid as not found', function (string $staffMemberId) {
        $response = ($this->show)($staffMemberId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('staff_member_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($response->error()->cause())->toBeInstanceOf(StaffMemberNotFound::class);
    })->with('malformed staff member ids when showing');

    it('turns a malformed id down before asking the roster or reading any hours', function (string $staffMemberId) {
        ($this->show)($staffMemberId);

        expect($this->roster->confirmations)->toBe([])
            ->and($this->rules->reads)->toBe([]);
    })->with('malformed staff member ids when showing');
});

dataset('malformed staff member ids when showing', [
    'empty' => '',
    'whitespace only' => '   ',
    'a row number' => '1',
    'a word' => 'ada',
]);
