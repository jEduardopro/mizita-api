<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\AccountReactivatedData;
use App\Domains\Accounts\Application\Dtos\ReactivateAccountInput;
use App\Domains\Accounts\Application\UseCases\ReactivateAccount;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Domains\Accounts\ValueObjects\TwoFactorStatus;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Accounts\AccountDeletionFixtures;
use Tests\Support\Accounts\AccountJournal;
use Tests\Support\Accounts\FakeOwnedBusinesses;
use Tests\Support\FakeTransactionManager;

beforeEach(function () {
    $this->journal = new AccountJournal;
    $this->accounts = Mockery::mock(AccountRepository::class);
    $this->ownedBusinesses = new FakeOwnedBusinesses($this->journal);
    $this->transactions = new FakeTransactionManager;

    $this->useCase = new ReactivateAccount($this->accounts, $this->ownedBusinesses, $this->transactions);

    $this->holding = function (Account $account): Account {
        $this->accounts->shouldReceive('findById')->once()
            ->with(AccountDeletionFixtures::ACCOUNT_ID)
            ->andReturn($account);

        return $account;
    };

    $this->savedAccounts = [];
    $this->savedInsideTransaction = [];
    $this->recordingSaves = function (): void {
        $this->accounts->shouldReceive('save')->once()
            ->andReturnUsing(function (Account $account): void {
                $this->journal->record('accounts.save');
                $this->savedAccounts[] = $account;
                $this->savedInsideTransaction[] = $this->transactions->isRunning();
            });
    };

    $this->reactivate = fn () => $this->useCase->handle(
        ReactivateAccountInput::forPendingAccount(AccountDeletionFixtures::ACCOUNT_ID),
    );
});

describe('an account scheduled for deletion', function () {
    beforeEach(function () {
        ($this->holding)(AccountDeletionFixtures::scheduledAccount());
        ($this->recordingSaves)();
    });

    it('clears the deletion request and saves the account', function () {
        $response = ($this->reactivate)();

        expect($response->succeeded())->toBeTrue()
            ->and($this->savedAccounts)->toHaveCount(1)
            ->and($this->savedAccounts[0]->id)->toBe(AccountDeletionFixtures::ACCOUNT_ID)
            ->and($this->savedAccounts[0]->isScheduledForDeletion())->toBeFalse()
            ->and($this->savedAccounts[0]->deletionRequestedAt())->toBeNull();
    });

    it('answers with the reactivated account by uuid, asking for no second factor', function () {
        $reactivated = ($this->reactivate)()->value();

        expect($reactivated)->toBeInstanceOf(AccountReactivatedData::class)
            ->and($reactivated->accountId)->toBe(AccountDeletionFixtures::ACCOUNT_ID)
            ->and($reactivated->requiresSecondFactor)->toBeFalse();
    });

    it('reopens nothing when the account owned no business', function () {
        ($this->reactivate)();

        expect($this->ownedBusinesses->reopenings)->toBe([])
            ->and($this->journal->entries)->toBe(['accounts.save', 'ownedBusinesses.closedBusinessOf']);
    });

    it('works inside a single transaction', function () {
        ($this->reactivate)();

        expect($this->transactions->runs())->toBe(1)
            ->and($this->savedInsideTransaction)->toBe([true]);
    });
});

describe('an account whose second factor decides whether the session may open', function () {
    it('reports whether the reactivated account still has to pass its second factor', function (TwoFactorStatus $status, bool $requiresSecondFactor) {
        ($this->holding)(Account::restore(
            id: AccountDeletionFixtures::ACCOUNT_ID,
            name: AccountDeletionFixtures::NAME,
            email: AccountDeletionFixtures::EMAIL,
            emailVerifiedAt: new DateTimeImmutable('2025-06-01T08:30:00+00:00'),
            createdAt: new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
            deletionRequestedAt: new DateTimeImmutable(AccountDeletionFixtures::DELETION_REQUESTED_AT),
            twoFactorStatus: $status,
        ));
        ($this->recordingSaves)();

        $reactivated = ($this->reactivate)()->value();

        expect($reactivated->accountId)->toBe(AccountDeletionFixtures::ACCOUNT_ID)
            ->and($reactivated->requiresSecondFactor)->toBe($requiresSecondFactor)
            ->and($this->savedAccounts[0]->isScheduledForDeletion())->toBeFalse();
    })->with([
        'enabled' => [TwoFactorStatus::Enabled, true],
        'set up but never confirmed' => [TwoFactorStatus::Pending, false],
        'disabled' => [TwoFactorStatus::Disabled, false],
    ]);
});

describe('an owner whose business closed with the account', function () {
    beforeEach(function () {
        ($this->holding)(AccountDeletionFixtures::scheduledAccount());
        ($this->recordingSaves)();
    });

    it('reopens the closed business, naming the owner by uuid', function () {
        $this->ownedBusinesses->closedFor(AccountDeletionFixtures::ACCOUNT_ID, AccountDeletionFixtures::closedBusiness());

        ($this->reactivate)();

        expect($this->ownedBusinesses->reopenings)->toBe([[
            'businessId' => AccountDeletionFixtures::BUSINESS_ID,
            'ownerAccountId' => AccountDeletionFixtures::ACCOUNT_ID,
        ]]);
    });

    it('saves the account before it reopens the business', function () {
        $this->ownedBusinesses->closedFor(AccountDeletionFixtures::ACCOUNT_ID, AccountDeletionFixtures::closedBusiness());

        ($this->reactivate)();

        expect($this->journal->entries)->toBe([
            'accounts.save',
            'ownedBusinesses.closedBusinessOf',
            'ownedBusinesses.reopen',
        ]);
    });

    it('reopens a business whose data was already purged, leaving the re-provisioning to it', function () {
        $this->ownedBusinesses->closedFor(AccountDeletionFixtures::ACCOUNT_ID, AccountDeletionFixtures::closedBusiness(purged: true));

        expect(($this->reactivate)()->succeeded())->toBeTrue()
            ->and($this->ownedBusinesses->reopenings)->toHaveCount(1);
    });
});

describe('a session with no account waiting', function () {
    it('answers account_reactivation_not_pending as not found, opening no transaction', function (?string $pendingAccountId) {
        $this->accounts->shouldNotReceive('findById');
        $this->accounts->shouldNotReceive('save');

        $response = $this->useCase->handle(ReactivateAccountInput::forPendingAccount($pendingAccountId));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('account_reactivation_not_pending')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->transactions->runs())->toBe(0)
            ->and($this->journal->entries)->toBe([]);
    })->with([
        'no marker' => null,
        'an empty marker' => '',
        'a malformed marker' => 'not-a-uuid',
    ]);
});

describe('an account that is not scheduled for deletion', function () {
    it('answers account_not_scheduled_for_deletion as a conflict, saving and reopening nothing', function () {
        ($this->holding)(AccountDeletionFixtures::activeAccount());
        $this->accounts->shouldNotReceive('save');
        $this->ownedBusinesses->closedFor(AccountDeletionFixtures::ACCOUNT_ID, AccountDeletionFixtures::closedBusiness());

        $response = ($this->reactivate)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('account_not_scheduled_for_deletion')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->ownedBusinesses->reopenings)->toBe([])
            ->and($this->journal->entries)->toBe([]);
    });
});

it('answers with not found when the pending account no longer exists', function () {
    $missing = AccountNotFound::withId(AccountDeletionFixtures::ACCOUNT_ID);
    $this->accounts->shouldReceive('findById')->once()->andThrow($missing);
    $this->accounts->shouldNotReceive('save');

    $response = ($this->reactivate)();

    expect($response->error()->code)->toBe('account_not_found')
        ->and($response->error()->cause())->toBe($missing)
        ->and($this->ownedBusinesses->reopenings)->toBe([]);
});

it('lets a storage failure escape before any business is reopened', function () {
    ($this->holding)(AccountDeletionFixtures::scheduledAccount());
    $this->accounts->shouldReceive('save')->once()->andThrow(new RuntimeException('SQLSTATE[40001] serialization failure'));
    $this->ownedBusinesses->closedFor(AccountDeletionFixtures::ACCOUNT_ID, AccountDeletionFixtures::closedBusiness());

    expect(fn () => ($this->reactivate)())
        ->toThrow(RuntimeException::class, 'SQLSTATE[40001] serialization failure')
        ->and($this->ownedBusinesses->reopenings)->toBe([]);
});
