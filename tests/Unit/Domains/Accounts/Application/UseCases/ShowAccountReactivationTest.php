<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\AccountReactivationData;
use App\Domains\Accounts\Application\Dtos\ShowAccountReactivationInput;
use App\Domains\Accounts\Application\UseCases\ShowAccountReactivation;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Accounts\AccountDeletionFixtures;
use Tests\Support\Accounts\FakeOwnedBusinesses;

beforeEach(function () {
    $this->accounts = Mockery::mock(AccountRepository::class);
    $this->accounts->shouldNotReceive('save');
    $this->ownedBusinesses = new FakeOwnedBusinesses;

    $this->useCase = new ShowAccountReactivation($this->accounts, $this->ownedBusinesses);

    $this->holding = function ($account): void {
        $this->accounts->shouldReceive('findById')->once()
            ->with(AccountDeletionFixtures::ACCOUNT_ID)
            ->andReturn($account);
    };

    $this->show = fn (): AccountReactivationData => $this->useCase
        ->handle(ShowAccountReactivationInput::forPendingAccount(AccountDeletionFixtures::ACCOUNT_ID))
        ->value();
});

describe('an owner whose business closed with the account', function () {
    beforeEach(function () {
        ($this->holding)(AccountDeletionFixtures::scheduledAccount());
    });

    it('shows the account and its closed business field by field', function () {
        $this->ownedBusinesses->closedFor(AccountDeletionFixtures::ACCOUNT_ID, AccountDeletionFixtures::closedBusiness());

        $reactivation = ($this->show)();

        expect($reactivation)->toBeInstanceOf(AccountReactivationData::class)
            ->and($reactivation->email)->toBe(AccountDeletionFixtures::EMAIL)
            ->and($reactivation->name)->toBe(AccountDeletionFixtures::NAME)
            ->and($reactivation->deletionRequestedAt->format(DATE_ATOM))->toBe(AccountDeletionFixtures::DELETION_REQUESTED_AT)
            ->and($reactivation->gracePeriodEndsAt->format(DATE_ATOM))->toBe(AccountDeletionFixtures::REQUESTED_GRACE_PERIOD_ENDS_AT)
            ->and($reactivation->business->id)->toBe(AccountDeletionFixtures::BUSINESS_ID)
            ->and($reactivation->business->name)->toBe(AccountDeletionFixtures::BUSINESS_NAME)
            ->and($reactivation->business->closedAt->format(DATE_ATOM))->toBe(AccountDeletionFixtures::CLOSED_AT)
            ->and($reactivation->business->purgeScheduledAt->format(DATE_ATOM))->toBe(AccountDeletionFixtures::PURGE_SCHEDULED_AT)
            ->and($reactivation->business->purged)->toBeFalse();
    });

    it('says so when the business data has already been purged', function () {
        $this->ownedBusinesses->closedFor(AccountDeletionFixtures::ACCOUNT_ID, AccountDeletionFixtures::closedBusiness(purged: true));

        expect(($this->show)()->business->purged)->toBeTrue();
    });

    it('reopens nothing just by showing', function () {
        $this->ownedBusinesses->closedFor(AccountDeletionFixtures::ACCOUNT_ID, AccountDeletionFixtures::closedBusiness());

        ($this->show)();

        expect($this->ownedBusinesses->reopenings)->toBe([])
            ->and($this->ownedBusinesses->journal->entries)->toBe(['ownedBusinesses.closedBusinessOf']);
    });
});

it('shows no business for an account that owned none', function () {
    ($this->holding)(AccountDeletionFixtures::scheduledAccount());

    $reactivation = ($this->show)();

    expect($reactivation->business)->toBeNull()
        ->and($reactivation->email)->toBe(AccountDeletionFixtures::EMAIL);
});

describe('a session with no account waiting', function () {
    it('answers account_reactivation_not_pending as not found, without looking anything up', function (?string $pendingAccountId) {
        $this->accounts->shouldNotReceive('findById');

        $response = $this->useCase->handle(ShowAccountReactivationInput::forPendingAccount($pendingAccountId));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('account_reactivation_not_pending')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->ownedBusinesses->journal->entries)->toBe([]);
    })->with([
        'no marker' => null,
        'an empty marker' => '',
        'a malformed marker' => 'not-a-uuid',
    ]);
});

describe('an account that is not scheduled for deletion', function () {
    it('answers account_not_scheduled_for_deletion as a conflict, without looking for a business', function () {
        ($this->holding)(AccountDeletionFixtures::activeAccount());

        $response = $this->useCase->handle(ShowAccountReactivationInput::forPendingAccount(AccountDeletionFixtures::ACCOUNT_ID));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('account_not_scheduled_for_deletion')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->ownedBusinesses->journal->entries)->toBe([]);
    });
});

it('answers with not found when the pending account no longer exists', function () {
    $missing = AccountNotFound::withId(AccountDeletionFixtures::ACCOUNT_ID);
    $this->accounts->shouldReceive('findById')->once()->andThrow($missing);

    $response = $this->useCase->handle(ShowAccountReactivationInput::forPendingAccount(AccountDeletionFixtures::ACCOUNT_ID));

    expect($response->error()->code)->toBe('account_not_found')
        ->and($response->error()->cause())->toBe($missing);
});
