<?php

declare(strict_types=1);

use App\Domains\Integrations\Exceptions\CalendarOwnerNotFound;
use App\Domains\Integrations\Infrastructure\Gateways\StaffCalendarOwners;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->members = Mockery::mock(StaffMemberRepository::class);
    $this->owners = new StaffCalendarOwners($this->members);
});

it('answers the uuid of the staff member the account is in this business', function () {
    $this->members->shouldReceive('findForAccount')->once()
        ->with(FakeBusinessContext::BUSINESS_ID, StaffFixtures::ACCOUNT_ID)
        ->andReturn(StaffFixtures::member(id: StaffFixtures::MEMBER_ID, role: StaffRole::Member));

    expect($this->owners->staffMemberIdOf(FakeBusinessContext::BUSINESS_ID, StaffFixtures::ACCOUNT_ID))
        ->toBe(StaffFixtures::MEMBER_ID);
});

it('translates an account with no staff row in the business into its own domain failure', function () {
    $missing = StaffMemberNotFound::forAccount(StaffFixtures::SECOND_ACCOUNT_ID);
    $this->members->shouldReceive('findForAccount')->once()
        ->with(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_ACCOUNT_ID)
        ->andThrow($missing);

    $failure = null;

    try {
        $this->owners->staffMemberIdOf(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_ACCOUNT_ID);
    } catch (CalendarOwnerNotFound $translated) {
        $failure = $translated;
    }

    expect($failure)->toBeInstanceOf(CalendarOwnerNotFound::class)
        ->and($failure?->errorCode())->toBe('staff_member_not_found')
        ->and($failure?->kind())->toBe(DomainFailureKind::NotFound)
        ->and($failure?->getPrevious())->toBe($missing);
});
