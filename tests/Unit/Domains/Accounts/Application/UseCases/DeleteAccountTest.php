<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\DeleteAccountInput;
use App\Domains\Accounts\Application\UseCases\DeleteAccount;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Domains\Accounts\ValueObjects\PasswordStatus;
use App\Domains\Accounts\ValueObjects\SocialProvider;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Accounts\AccountDeletionFixtures;
use Tests\Support\Accounts\AccountJournal;
use Tests\Support\Accounts\FakeAccountSessions;
use Tests\Support\Accounts\FakeOwnedBusinesses;
use Tests\Support\Accounts\FakePasswordVerifier;
use Tests\Support\Accounts\FakeTeamMemberships;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;

beforeEach(function () {
    $this->journal = new AccountJournal;
    $this->accounts = Mockery::mock(AccountRepository::class);
    $this->passwords = (new FakePasswordVerifier($this->journal))
        ->accepting(AccountDeletionFixtures::ACCOUNT_ID, AccountDeletionFixtures::PASSWORD);
    $this->memberships = new FakeTeamMemberships($this->journal);
    $this->ownedBusinesses = new FakeOwnedBusinesses($this->journal);
    $this->transactions = new FakeTransactionManager;
    $this->sessions = new FakeAccountSessions($this->journal, $this->transactions);

    $this->useCase = new DeleteAccount(
        $this->accounts,
        $this->passwords,
        $this->memberships,
        $this->ownedBusinesses,
        $this->sessions,
        new FakeClock(AccountDeletionFixtures::now()),
        $this->transactions,
    );

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

    $this->expectNothingWritten = function (): void {
        $this->accounts->shouldNotReceive('save');
    };
});

describe('an end customer with no business', function () {
    beforeEach(function () {
        ($this->holding)(AccountDeletionFixtures::activeAccount());
        ($this->recordingSaves)();
    });

    it('schedules the account for deletion at the clock instant and answers with no data', function () {
        $response = $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword());

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull()
            ->and($response->warnings())->toBe([])
            ->and($this->savedAccounts)->toHaveCount(1)
            ->and($this->savedAccounts[0]->id)->toBe(AccountDeletionFixtures::ACCOUNT_ID)
            ->and($this->savedAccounts[0]->isScheduledForDeletion())->toBeTrue()
            ->and($this->savedAccounts[0]->deletionRequestedAt())->toEqual(AccountDeletionFixtures::now())
            ->and($this->savedAccounts[0]->gracePeriodEndsAt()->format(DATE_ATOM))->toBe(AccountDeletionFixtures::GRACE_PERIOD_ENDS_AT);
    });

    it('ends every session of the account exactly once, by its uuid', function () {
        $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword());

        expect($this->sessions->endedForAll)->toBe([[AccountDeletionFixtures::ACCOUNT_ID]])
            ->and($this->sessions->endedExcept)->toBe([]);
    });

    it('saves inside one transaction and ends the sessions only after it commits', function () {
        $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword());

        expect($this->transactions->runs())->toBe(1)
            ->and($this->savedInsideTransaction)->toBe([true])
            ->and($this->sessions->endedInsideTransaction)->toBe([false]);
    });

    it('closes no business', function () {
        $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword());

        expect($this->ownedBusinesses->closures)->toBe([]);
    });

    it('still asks to leave the teams it does not own, which is a no-op for a customer', function () {
        $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword());

        expect($this->memberships->accountsThatLeft)->toBe([AccountDeletionFixtures::ACCOUNT_ID]);
    });
});

describe('a staff member of businesses the account does not own', function () {
    it('leaves those teams before the account is scheduled', function () {
        ($this->holding)(AccountDeletionFixtures::activeAccount());
        ($this->recordingSaves)();

        $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword());

        expect($this->memberships->accountsThatLeft)->toBe([AccountDeletionFixtures::ACCOUNT_ID])
            ->and($this->journal->entries)->toBe([
                'passwords.matches',
                'memberships.leaveTeamsNotOwned',
                'memberships.ownedBusinessIdOf',
                'accounts.save',
                'sessions.endAll',
            ]);
    });

    describe('with upcoming appointments assigned', function () {
        beforeEach(function () {
            $this->account = ($this->holding)(AccountDeletionFixtures::activeAccount());
            $this->memberships->busyElsewhere(AccountDeletionFixtures::ACCOUNT_ID);
            ($this->expectNothingWritten)();
        });

        it('refuses with account_has_upcoming_appointments as a conflict', function () {
            $response = $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword());

            expect($response->failed())->toBeTrue()
                ->and($response->error()->code)->toBe('account_has_upcoming_appointments')
                ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
                ->and($response->error()->cause()->getMessage())
                ->toBe('Account [01930000-0000-7000-8000-00000000ac01] still has upcoming appointments assigned in a business it does not own.');
        });

        it('ends no session', function () {
            $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword());

            expect($this->sessions->endedForAll)->toBe([])
                ->and($this->sessions->endedExcept)->toBe([]);
        });

        it('leaves no team and closes no business', function () {
            $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword());

            expect($this->memberships->accountsThatLeft)->toBe([])
                ->and($this->ownedBusinesses->closures)->toBe([]);
        });

        it('leaves the account active', function () {
            $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword());

            expect($this->account->isScheduledForDeletion())->toBeFalse();
        });

        it('keeps an owner business open when the owner is blocked elsewhere', function () {
            $this->memberships->owning(AccountDeletionFixtures::ACCOUNT_ID, AccountDeletionFixtures::BUSINESS_ID);

            $response = $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword());

            expect($response->error()->code)->toBe('account_has_upcoming_appointments')
                ->and($this->ownedBusinesses->closures)->toBe([]);
        });
    });
});

describe('the owner of a business', function () {
    beforeEach(function () {
        ($this->holding)(AccountDeletionFixtures::activeAccount());
        ($this->recordingSaves)();
        $this->memberships->owning(AccountDeletionFixtures::ACCOUNT_ID, AccountDeletionFixtures::BUSINESS_ID);
    });

    it('closes the owned business, naming the owner by uuid', function () {
        $response = $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword());

        expect($response->succeeded())->toBeTrue()
            ->and($this->ownedBusinesses->closures)->toBe([[
                'businessId' => AccountDeletionFixtures::BUSINESS_ID,
                'ownerAccountId' => AccountDeletionFixtures::ACCOUNT_ID,
            ]]);
    });

    it('also schedules the owner account for deletion', function () {
        $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword());

        expect($this->savedAccounts[0]->deletionRequestedAt())->toEqual(AccountDeletionFixtures::now());
    });

    it('leaves the other teams, then closes the business, then saves, then ends the sessions', function () {
        $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword());

        expect($this->journal->entries)->toBe([
            'passwords.matches',
            'memberships.leaveTeamsNotOwned',
            'memberships.ownedBusinessIdOf',
            'ownedBusinesses.close',
            'accounts.save',
            'sessions.endAll',
        ]);
    });

    it('ends only the owner sessions itself, leaving the team sign out to the business closure', function () {
        $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword());

        expect($this->sessions->endedForAll)->toBe([[AccountDeletionFixtures::ACCOUNT_ID]]);
    });
});

describe('confirming with a password', function () {
    it('asks the verifier about the account uuid and the typed password', function () {
        ($this->holding)(AccountDeletionFixtures::activeAccount());
        ($this->recordingSaves)();

        $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword());

        expect($this->passwords->attempts)->toBe([[
            'accountId' => AccountDeletionFixtures::ACCOUNT_ID,
            'plaintext' => AccountDeletionFixtures::PASSWORD,
        ]]);
    });

    it('requires the password of an account holding a temporary one too', function () {
        ($this->holding)(AccountDeletionFixtures::activeAccount(PasswordStatus::Temporary));
        ($this->recordingSaves)();

        expect($this->useCase->handle(AccountDeletionFixtures::confirmedByPassword())->succeeded())->toBeTrue()
            ->and($this->passwords->attempts)->toHaveCount(1);
    });

    it('requires the password even when Google is linked as well', function () {
        ($this->holding)(AccountDeletionFixtures::activeAccount(PasswordStatus::Chosen, linkedSocialProviders: [SocialProvider::Google]));
        ($this->expectNothingWritten)();

        expect($this->useCase->handle(AccountDeletionFixtures::confirmedByEmail())->error()->code)
            ->toBe('incorrect_account_password');
    });

    describe('a password that does not match', function () {
        beforeEach(function () {
            $this->account = ($this->holding)(AccountDeletionFixtures::activeAccount());
            ($this->expectNothingWritten)();
        });

        it('refuses with incorrect_account_password as invalid input', function (DeleteAccountInput $input) {
            $response = $this->useCase->handle($input);

            expect($response->failed())->toBeTrue()
                ->and($response->error()->code)->toBe('incorrect_account_password')
                ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid);
        })->with([
            'a wrong password' => fn () => AccountDeletionFixtures::confirmedByPassword('Wr0ng!Pass'),
            'an empty password' => fn () => AccountDeletionFixtures::confirmedByPassword(''),
            'no password at all' => fn () => new DeleteAccountInput(AccountDeletionFixtures::ACCOUNT_ID, null, null),
            'the email instead of the password' => fn () => AccountDeletionFixtures::confirmedByEmail(),
        ]);

        it('hands the typed password to the verifier untouched, never trimmed or lowercased', function () {
            $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword(' Correct1!Pass '));

            expect($this->passwords->attempts)->toBe([[
                'accountId' => AccountDeletionFixtures::ACCOUNT_ID,
                'plaintext' => ' Correct1!Pass ',
            ]]);
        });

        it('asks the verifier with an empty string when no password was sent', function () {
            $this->useCase->handle(new DeleteAccountInput(AccountDeletionFixtures::ACCOUNT_ID, null, AccountDeletionFixtures::EMAIL));

            expect($this->passwords->attempts)->toBe([[
                'accountId' => AccountDeletionFixtures::ACCOUNT_ID,
                'plaintext' => '',
            ]]);
        });

        it('touches no team, no business and no session, and opens no transaction', function () {
            $this->memberships->owning(AccountDeletionFixtures::ACCOUNT_ID, AccountDeletionFixtures::BUSINESS_ID);

            $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword('Wr0ng!Pass'));

            expect($this->journal->entries)->toBe(['passwords.matches'])
                ->and($this->ownedBusinesses->closures)->toBe([])
                ->and($this->sessions->endedForAll)->toBe([])
                ->and($this->transactions->runs())->toBe(0)
                ->and($this->account->isScheduledForDeletion())->toBeFalse();
        });
    });
});

describe('confirming with the email, for an account with no password', function () {
    it('schedules the deletion when the typed email matches, without asking for a password', function (string $typed) {
        ($this->holding)(AccountDeletionFixtures::activeAccount(PasswordStatus::Absent, linkedSocialProviders: [SocialProvider::Google]));
        ($this->recordingSaves)();

        $response = $this->useCase->handle(AccountDeletionFixtures::confirmedByEmail($typed));

        expect($response->succeeded())->toBeTrue()
            ->and($this->savedAccounts[0]->isScheduledForDeletion())->toBeTrue()
            ->and($this->passwords->attempts)->toBe([])
            ->and($this->sessions->endedForAll)->toBe([[AccountDeletionFixtures::ACCOUNT_ID]]);
    })->with([
        'exactly' => 'ada@example.com',
        'upper case' => 'ADA@EXAMPLE.COM',
        'padded' => '  ada@example.com ',
    ]);

    it('ignores a password sent to an account that has none', function () {
        ($this->holding)(AccountDeletionFixtures::activeAccount(PasswordStatus::Absent));
        ($this->recordingSaves)();

        $response = $this->useCase->handle(new DeleteAccountInput(AccountDeletionFixtures::ACCOUNT_ID, 'anything', AccountDeletionFixtures::EMAIL));

        expect($response->succeeded())->toBeTrue()
            ->and($this->passwords->attempts)->toBe([]);
    });

    describe('an email that does not match', function () {
        beforeEach(function () {
            $this->account = ($this->holding)(AccountDeletionFixtures::activeAccount(PasswordStatus::Absent, linkedSocialProviders: [SocialProvider::Google]));
            ($this->expectNothingWritten)();
        });

        it('refuses with account_deletion_email_mismatch as invalid input', function (DeleteAccountInput $input) {
            $response = $this->useCase->handle($input);

            expect($response->failed())->toBeTrue()
                ->and($response->error()->code)->toBe('account_deletion_email_mismatch')
                ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid);
        })->with([
            'another address' => fn () => AccountDeletionFixtures::confirmedByEmail('grace@example.com'),
            'an empty email' => fn () => AccountDeletionFixtures::confirmedByEmail(''),
            'whitespace only' => fn () => AccountDeletionFixtures::confirmedByEmail('   '),
            'no email at all' => fn () => new DeleteAccountInput(AccountDeletionFixtures::ACCOUNT_ID, null, null),
            'a password instead of the email' => fn () => AccountDeletionFixtures::confirmedByPassword(),
        ]);

        it('touches no team, no business and no session, and opens no transaction', function () {
            $this->useCase->handle(AccountDeletionFixtures::confirmedByEmail('grace@example.com'));

            expect($this->journal->entries)->toBe([])
                ->and($this->sessions->endedForAll)->toBe([])
                ->and($this->transactions->runs())->toBe(0)
                ->and($this->account->isScheduledForDeletion())->toBeFalse();
        });
    });
});

describe('an account already scheduled for deletion', function () {
    beforeEach(function () {
        $this->account = ($this->holding)(AccountDeletionFixtures::scheduledAccount());
        ($this->expectNothingWritten)();
    });

    it('refuses with account_pending_reactivation as a conflict', function () {
        $response = $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword());

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('account_pending_reactivation')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('refuses before checking the confirmation or touching anything', function () {
        $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword());

        expect($this->journal->entries)->toBe([])
            ->and($this->transactions->runs())->toBe(0)
            ->and($this->account->deletionRequestedAt()->format(DATE_ATOM))->toBe(AccountDeletionFixtures::DELETION_REQUESTED_AT);
    });
});

describe('an input the account cannot be found from', function () {
    it('refuses a malformed account id as not found, without looking it up', function () {
        $this->accounts->shouldNotReceive('findById');
        ($this->expectNothingWritten)();

        $response = $this->useCase->handle(new DeleteAccountInput('7', AccountDeletionFixtures::PASSWORD, null));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('account_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('refuses an overlong password as incorrect, without asking the verifier', function () {
        $this->accounts->shouldNotReceive('findById');

        $response = $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword(str_repeat('a', 256)));

        expect($response->error()->code)->toBe('incorrect_account_password')
            ->and($this->passwords->attempts)->toBe([]);
    });

    it('answers with not found when the account does not exist', function () {
        $missing = AccountNotFound::withId(AccountDeletionFixtures::ACCOUNT_ID);
        $this->accounts->shouldReceive('findById')->once()->andThrow($missing);
        ($this->expectNothingWritten)();

        $response = $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword());

        expect($response->error()->code)->toBe('account_not_found')
            ->and($response->error()->cause())->toBe($missing)
            ->and($this->sessions->endedForAll)->toBe([]);
    });
});

it('lets a storage failure escape and ends no session', function () {
    ($this->holding)(AccountDeletionFixtures::activeAccount());
    $this->accounts->shouldReceive('save')->once()->andThrow(new RuntimeException('SQLSTATE[08006] connection failure'));

    expect(fn () => $this->useCase->handle(AccountDeletionFixtures::confirmedByPassword()))
        ->toThrow(RuntimeException::class, 'SQLSTATE[08006] connection failure')
        ->and($this->sessions->endedForAll)->toBe([]);
});
