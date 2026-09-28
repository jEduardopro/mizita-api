<?php

declare(strict_types=1);

use App\Domains\Availability\Contracts\StaffSchedules;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\WeeklyIntervals;
use App\Domains\Staff\Infrastructure\Gateways\AvailabilityWorkingHours;
use Tests\Support\Availability\ScheduleFixtures;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->schedules = Mockery::mock(StaffSchedules::class);
    $this->gateway = new AvailabilityWorkingHours($this->schedules);

    $this->businessHours = ScheduleFixtures::weeklyIntervals([1 => [['09:00', '18:00']]]);
    $this->ownHoursOf = fn (string $staffMemberId): WeeklyIntervals => ScheduleFixtures::weeklyIntervals(
        [2 => [['10:00', '14:00']]],
        ScheduleOwnerType::StaffMember,
        $staffMemberId,
    );
});

it('counts every member as working when the business has hours, without reading anyone own hours', function () {
    $this->schedules->shouldReceive('forBusiness')->once()->with(FakeBusinessContext::BUSINESS_ID)->andReturn($this->businessHours);
    $this->schedules->shouldNotReceive('forStaffMember');

    expect($this->gateway->staffWithWorkingHours(FakeBusinessContext::BUSINESS_ID, [StaffFixtures::MEMBER_ID, StaffFixtures::SECOND_MEMBER_ID]))
        ->toBe([StaffFixtures::MEMBER_ID, StaffFixtures::SECOND_MEMBER_ID]);
});

it('counts only the members with hours of their own when the business has none', function () {
    $this->schedules->shouldReceive('forBusiness')->once()->andReturn(WeeklyIntervals::none());
    $this->schedules->shouldReceive('forStaffMember')->with(StaffFixtures::MEMBER_ID)->andReturn(($this->ownHoursOf)(StaffFixtures::MEMBER_ID));
    $this->schedules->shouldReceive('forStaffMember')->with(StaffFixtures::SECOND_MEMBER_ID)->andReturn(WeeklyIntervals::none());
    $this->schedules->shouldReceive('forStaffMember')->with(StaffFixtures::THIRD_MEMBER_ID)->andReturn(($this->ownHoursOf)(StaffFixtures::THIRD_MEMBER_ID));

    expect($this->gateway->staffWithWorkingHours(FakeBusinessContext::BUSINESS_ID, [
        StaffFixtures::MEMBER_ID,
        StaffFixtures::SECOND_MEMBER_ID,
        StaffFixtures::THIRD_MEMBER_ID,
    ]))->toBe([StaffFixtures::MEMBER_ID, StaffFixtures::THIRD_MEMBER_ID]);
});

it('counts nobody when neither the business nor any member has hours', function () {
    $this->schedules->shouldReceive('forBusiness')->once()->andReturn(WeeklyIntervals::none());
    $this->schedules->shouldReceive('forStaffMember')->andReturn(WeeklyIntervals::none());

    expect($this->gateway->staffWithWorkingHours(FakeBusinessContext::BUSINESS_ID, [StaffFixtures::MEMBER_ID]))->toBe([]);
});

it('asks nothing for an empty team', function () {
    $this->schedules->shouldNotReceive('forBusiness');
    $this->schedules->shouldNotReceive('forStaffMember');

    expect($this->gateway->staffWithWorkingHours(FakeBusinessContext::BUSINESS_ID, []))->toBe([]);
});

it('answers with a list even when the ids arrive under keys', function () {
    $this->schedules->shouldReceive('forBusiness')->once()->andReturn($this->businessHours);

    $working = $this->gateway->staffWithWorkingHours(FakeBusinessContext::BUSINESS_ID, [3 => StaffFixtures::MEMBER_ID]);

    expect($working)->toBe([StaffFixtures::MEMBER_ID])
        ->and(array_is_list($working))->toBeTrue();
});
