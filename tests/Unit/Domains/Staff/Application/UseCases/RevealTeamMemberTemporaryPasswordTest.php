<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\RevealedTemporaryPassword;
use App\Domains\Staff\Application\Dtos\RevealTeamMemberTemporaryPasswordInput;
use App\Domains\Staff\Application\UseCases\RevealTeamMemberTemporaryPassword;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Staff\FakeAccountDirectory;
use Tests\Support\Staff\FakeStaffMemberRepository;
use Tests\Support\Staff\FakeTeamTemporaryPasswords;
use Tests\Support\Staff\StaffFixtures;

const OTHER_BUSINESS_TEMPORARY_PASSWORD = 'Other-Pa55word!';

beforeEach(function () {
    $this->members = (new FakeStaffMemberRepository)->store(
        StaffFixtures::member(),
        StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::Member),
        StaffFixtures::member(id: StaffFixtures::THIRD_MEMBER_ID, accountId: StaffFixtures::THIRD_ACCOUNT_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID, role: StaffRole::Member),
    );
    $this->accounts = new FakeAccountDirectory(
        StaffFixtures::account(),
        StaffFixtures::account(id: StaffFixtures::SECOND_ACCOUNT_ID, name: 'Grace Hopper', email: 'grace@example.com', awaitingPasswordChange: true),
        StaffFixtures::account(id: StaffFixtures::THIRD_ACCOUNT_ID, name: 'Linus Pauling', email: 'linus@example.com', awaitingPasswordChange: true),
    );
    $this->temporaryPasswords = (new FakeTeamTemporaryPasswords)
        ->holds(StaffFixtures::SECOND_ACCOUNT_ID, StaffFixtures::TEMPORARY_PASSWORD)
        ->holds(StaffFixtures::THIRD_ACCOUNT_ID, OTHER_BUSINESS_TEMPORARY_PASSWORD);

    $this->build = fn (?FakeBusinessContext $business = null): RevealTeamMemberTemporaryPassword => new RevealTeamMemberTemporaryPassword(
        $this->members,
        $this->accounts,
        $this->temporaryPasswords,
        $business ?? new FakeBusinessContext,
    );

    $this->reveal = fn (string $staffMemberId = StaffFixtures::SECOND_MEMBER_ID, ?RevealTeamMemberTemporaryPassword $useCase = null): UseCaseResponse => ($useCase ?? ($this->build)())
        ->handle(new RevealTeamMemberTemporaryPasswordInput($staffMemberId));
});

describe('a member whose invitation is pending', function () {
    it('answers with the plaintext temporary password its account holds', function () {
        $revealed = ($this->reveal)()->value();

        expect($revealed)->toBeInstanceOf(RevealedTemporaryPassword::class)
            ->and($revealed->temporaryPassword)->toBe(StaffFixtures::TEMPORARY_PASSWORD);
    });

    it('reads the password of the account behind the member, by its uuid', function () {
        ($this->reveal)();

        expect($this->temporaryPasswords->reveals)->toBe([StaffFixtures::SECOND_ACCOUNT_ID])
            ->and($this->accounts->calls)->toBe([[StaffFixtures::SECOND_ACCOUNT_ID]]);
    });

    it('writes nothing', function () {
        ($this->reveal)();

        expect($this->members->saved)->toBe([])
            ->and($this->members->deleted)->toBe([])
            ->and($this->accounts->renames)->toBe([]);
    });

    it('answers with a conflict when the account no longer holds a password', function () {
        $this->temporaryPasswords = new FakeTeamTemporaryPasswords;

        $response = ($this->reveal)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('temporary_password_unavailable')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('lets a vault that breaks escape as a programmer error', function () {
        $bug = new RuntimeException('the payload could not be decrypted');
        $this->temporaryPasswords->failRevealWith($bug);

        expect(fn () => ($this->reveal)())->toThrow($bug);
    });
});

describe('a member with no pending invitation', function () {
    it('answers with a conflict without reading any password', function (string $staffMemberId, string $accountId, StaffRole $role, bool $awaitingPasswordChange) {
        $this->members->store(StaffFixtures::member(id: $staffMemberId, accountId: $accountId, role: $role));
        $this->accounts = new FakeAccountDirectory(StaffFixtures::account(id: $accountId, awaitingPasswordChange: $awaitingPasswordChange));
        $this->temporaryPasswords->holds($accountId, StaffFixtures::TEMPORARY_PASSWORD);

        $response = ($this->reveal)($staffMemberId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('temporary_password_unavailable')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->temporaryPasswords->reveals)->toBe([]);
    })->with([
        'a member who already signed in' => [StaffFixtures::SECOND_MEMBER_ID, StaffFixtures::SECOND_ACCOUNT_ID, StaffRole::Member, false],
        'a no access member' => [StaffFixtures::SECOND_MEMBER_ID, StaffFixtures::SECOND_ACCOUNT_ID, StaffRole::NoAccess, true],
        'the owner' => [StaffFixtures::MEMBER_ID, StaffFixtures::ACCOUNT_ID, StaffRole::Owner, true],
    ]);
});

describe('a member it cannot find', function () {
    it('refuses a value that is not a uuid without looking anything up', function (string $staffMemberId) {
        $response = ($this->reveal)($staffMemberId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('staff_member_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->members->businessLookups)->toBe([])
            ->and($this->accounts->calls)->toBe([])
            ->and($this->temporaryPasswords->reveals)->toBe([]);
    })->with([
        'empty' => '',
        'an int id' => '42',
        'garbage' => 'not-a-uuid',
    ]);

    it('refuses a member nobody has', function () {
        $response = ($this->reveal)('01930000-0000-7000-8000-0000000000d9');

        expect($response->error()->code)->toBe('staff_member_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->temporaryPasswords->reveals)->toBe([]);
    });

    it('refuses a member whose account is gone as a member that is not there', function () {
        $this->accounts = new FakeAccountDirectory;

        $response = ($this->reveal)();

        expect($response->error()->code)->toBe('staff_member_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->temporaryPasswords->reveals)->toBe([]);
    });
});

describe('tenant isolation', function () {
    it('refuses a member of another business, looking it up only in the business of the context', function () {
        $response = ($this->reveal)(StaffFixtures::THIRD_MEMBER_ID);

        expect($response->error()->code)->toBe('staff_member_not_found')
            ->and($this->members->businessLookups)->toBe([['businessId' => FakeBusinessContext::BUSINESS_ID, 'id' => StaffFixtures::THIRD_MEMBER_ID]])
            ->and($this->temporaryPasswords->reveals)->toBe([]);
    });

    it('reveals that member only when its business is the one in context', function () {
        $revealed = ($this->reveal)(StaffFixtures::THIRD_MEMBER_ID, ($this->build)(new FakeBusinessContext(StaffFixtures::OTHER_BUSINESS_ID)))->value();

        expect($revealed->temporaryPassword)->toBe(OTHER_BUSINESS_TEMPORARY_PASSWORD)
            ->and($this->members->businessLookups)->toBe([['businessId' => StaffFixtures::OTHER_BUSINESS_ID, 'id' => StaffFixtures::THIRD_MEMBER_ID]]);
    });

    it('refuses a member of the context business once the context moves elsewhere', function () {
        $response = ($this->reveal)(StaffFixtures::SECOND_MEMBER_ID, ($this->build)(new FakeBusinessContext(StaffFixtures::OTHER_BUSINESS_ID)));

        expect($response->error()->code)->toBe('staff_member_not_found')
            ->and($this->temporaryPasswords->reveals)->toBe([]);
    });
});
