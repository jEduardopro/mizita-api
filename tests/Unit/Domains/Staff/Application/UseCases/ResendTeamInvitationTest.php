<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\ResendTeamInvitationInput;
use App\Domains\Staff\Application\UseCases\ResendTeamInvitation;
use App\Domains\Staff\Events\TeamMemberInvited;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Application\UseCaseError;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Staff\FakeAccountDirectory;
use Tests\Support\Staff\FakeStaffMemberRepository;
use Tests\Support\Staff\FakeTeamAccountProvisioner;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->members = (new FakeStaffMemberRepository)->store(
        StaffFixtures::member(),
        StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::Member),
        StaffFixtures::member(id: StaffFixtures::THIRD_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID, role: StaffRole::Member),
    );
    $this->accounts = new FakeAccountDirectory(
        StaffFixtures::account(awaitingPasswordChange: true),
        StaffFixtures::account(id: StaffFixtures::SECOND_ACCOUNT_ID, name: 'Grace Hopper', email: 'grace@example.com', awaitingPasswordChange: true),
    );
    $this->provisioner = (new FakeTeamAccountProvisioner)->issues(StaffFixtures::SECOND_ACCOUNT_ID, StaffFixtures::TEMPORARY_PASSWORD);

    $this->dispatched = [];
    $this->events = Mockery::mock(Dispatcher::class);
    $this->events->shouldReceive('dispatch')->andReturnUsing(function (object $event): void {
        $this->dispatched[] = $event;
    });

    $this->build = fn (?FakeBusinessContext $business = null): ResendTeamInvitation => new ResendTeamInvitation(
        $this->members,
        $this->accounts,
        $this->provisioner,
        $business ?? new FakeBusinessContext,
        $this->events,
    );

    $this->resend = fn (string $staffMemberId = StaffFixtures::SECOND_MEMBER_ID, ?ResendTeamInvitation $useCase = null): UseCaseResponse => ($useCase ?? ($this->build)())
        ->handle(new ResendTeamInvitationInput($staffMemberId));

    $this->refusal = function (string $staffMemberId = StaffFixtures::SECOND_MEMBER_ID): UseCaseError {
        $response = ($this->resend)($staffMemberId);

        expect($response->failed())->toBeTrue()
            ->and($this->dispatched)->toBe([]);

        return $response->error();
    };
});

it('issues a fresh temporary password and announces the invitation again', function () {
    $response = ($this->resend)();

    expect($response->succeeded())->toBeTrue()
        ->and($response->value())->toBeNull()
        ->and($this->provisioner->issued)->toBe([StaffFixtures::SECOND_ACCOUNT_ID])
        ->and($this->dispatched)->toHaveCount(1)
        ->and($this->dispatched[0])->toBeInstanceOf(TeamMemberInvited::class)
        ->and($this->dispatched[0]->staffMemberId)->toBe(StaffFixtures::SECOND_MEMBER_ID)
        ->and($this->dispatched[0]->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
        ->and($this->dispatched[0]->accountId)->toBe(StaffFixtures::SECOND_ACCOUNT_ID)
        ->and($this->dispatched[0]->temporaryPassword)->toBe(StaffFixtures::TEMPORARY_PASSWORD);
});

it('describes only the account behind the member', function () {
    ($this->resend)();

    expect($this->accounts->calls)->toBe([[StaffFixtures::SECOND_ACCOUNT_ID]]);
});

it('refuses a member who already chose a password, issuing nothing', function () {
    $this->accounts = new FakeAccountDirectory(StaffFixtures::account(id: StaffFixtures::SECOND_ACCOUNT_ID, awaitingPasswordChange: false));

    $error = ($this->refusal)();

    expect($error->code)->toBe('team_invitation_not_pending')
        ->and($error->kind)->toBe(DomainFailureKind::Conflict)
        ->and($this->provisioner->issued)->toBe([]);
});

it('refuses a member who was never given access, issuing nothing', function () {
    $this->members->store(StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::NoAccess));

    expect(($this->refusal)()->code)->toBe('team_invitation_not_pending')
        ->and($this->provisioner->issued)->toBe([]);
});

it('refuses the owner, issuing nothing', function () {
    expect(($this->refusal)(StaffFixtures::MEMBER_ID)->code)->toBe('team_invitation_not_pending')
        ->and($this->provisioner->issued)->toBe([]);
});

it('refuses a member whose account is gone, issuing nothing', function () {
    $this->accounts = new FakeAccountDirectory;

    $error = ($this->refusal)();

    expect($error->code)->toBe('staff_member_not_found')
        ->and($error->kind)->toBe(DomainFailureKind::NotFound)
        ->and($this->provisioner->issued)->toBe([]);
});

it('refuses a value that is not a uuid without looking anything up', function () {
    $error = ($this->refusal)('42');

    expect($error->code)->toBe('staff_member_not_found')
        ->and($this->members->businessLookups)->toBe([])
        ->and($this->accounts->calls)->toBe([]);
});

it('refuses a member nobody has', function () {
    expect(($this->refusal)('01930000-0000-7000-8000-0000000000d9')->code)->toBe('staff_member_not_found');
});

it('announces nothing when no password can be issued', function () {
    $this->provisioner->refuseIssuingWith(StaffMemberNotFound::forAccount(StaffFixtures::SECOND_ACCOUNT_ID));

    expect(($this->refusal)()->code)->toBe('staff_member_not_found');
});

it('announces the invitation without a password when the account took one of its own in the meantime', function () {
    $this->provisioner->issues(StaffFixtures::SECOND_ACCOUNT_ID, null);

    ($this->resend)()->value();

    expect($this->dispatched)->toHaveCount(1)
        ->and($this->dispatched[0]->temporaryPassword)->toBeNull();
});

describe('tenant isolation', function () {
    it('refuses a member of another business, looking it up only in the business of the context', function () {
        $error = ($this->refusal)(StaffFixtures::THIRD_MEMBER_ID);

        expect($error->code)->toBe('staff_member_not_found')
            ->and($this->members->businessLookups)->toBe([['businessId' => FakeBusinessContext::BUSINESS_ID, 'id' => StaffFixtures::THIRD_MEMBER_ID]])
            ->and($this->provisioner->issued)->toBe([]);
    });

    it('announces the invitation in the business of the context', function () {
        ($this->resend)(StaffFixtures::THIRD_MEMBER_ID, ($this->build)(new FakeBusinessContext(StaffFixtures::OTHER_BUSINESS_ID)))->value();

        expect($this->dispatched[0]->staffMemberId)->toBe(StaffFixtures::THIRD_MEMBER_ID)
            ->and($this->dispatched[0]->businessId)->toBe(StaffFixtures::OTHER_BUSINESS_ID);
    });
});
