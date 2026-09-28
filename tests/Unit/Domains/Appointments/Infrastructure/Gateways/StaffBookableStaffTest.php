<?php

declare(strict_types=1);

use App\Domains\Appointments\Exceptions\AppointmentStaffMemberPaused;
use App\Domains\Appointments\Infrastructure\Gateways\StaffBookableStaff;
use App\Domains\Staff\Application\Services\BookableTeam;
use App\Domains\Staff\Contracts\TeamAllowance;
use App\Domains\Staff\Contracts\TeamOwnership;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Appointments\AppointmentFixtures;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->allowance = Mockery::mock(TeamAllowance::class);
    $this->ownership = Mockery::mock(TeamOwnership::class);
    $this->ownership->shouldReceive('ownerStaffMemberIdOf')
        ->with(FakeBusinessContext::BUSINESS_ID)
        ->andReturn(AppointmentFixtures::STAFF_ID);

    $this->confirm = fn (string $staffMemberId) => (new StaffBookableStaff(
        new BookableTeam($this->allowance, $this->ownership),
    ))->confirmBookable(FakeBusinessContext::BUSINESS_ID, $staffMemberId);
});

describe('a business whose plan includes the team', function () {
    beforeEach(function () {
        $this->allowance->shouldReceive('includesTeam')->with(FakeBusinessContext::BUSINESS_ID)->andReturnTrue();
    });

    it('lets any team member take appointments', function (string $staffMemberId) {
        expect(fn () => ($this->confirm)($staffMemberId))->not->toThrow(Throwable::class);
    })->with([
        'the owner' => AppointmentFixtures::STAFF_ID,
        'another team member' => AppointmentFixtures::SECOND_STAFF_ID,
    ]);
});

describe('a business whose plan leaves the team out', function () {
    beforeEach(function () {
        $this->allowance->shouldReceive('includesTeam')->with(FakeBusinessContext::BUSINESS_ID)->andReturnFalse();
    });

    it('lets the owner take appointments', function () {
        expect(fn () => ($this->confirm)(AppointmentFixtures::STAFF_ID))->not->toThrow(Throwable::class);
    });

    it('refuses every other team member as paused', function () {
        expect(fn () => ($this->confirm)(AppointmentFixtures::SECOND_STAFF_ID))
            ->toThrow(AppointmentStaffMemberPaused::class);
    });

    it('refuses with a forbidden domain failure the client can read', function () {
        $thrown = null;

        try {
            ($this->confirm)(AppointmentFixtures::SECOND_STAFF_ID);
        } catch (AppointmentStaffMemberPaused $refusal) {
            $thrown = $refusal;
        }

        expect($thrown)->toBeInstanceOf(DomainFailure::class)
            ->and($thrown?->errorCode())->toBe('staff_member_paused')
            ->and($thrown?->kind())->toBe(DomainFailureKind::Forbidden);
    });
});
