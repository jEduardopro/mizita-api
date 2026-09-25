<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\IssuedTemporaryPasswordData;
use App\Domains\Accounts\Application\Dtos\IssueTemporaryPasswordInput;
use App\Domains\Accounts\Application\UseCases\IssueTemporaryPassword;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Contracts\PasswordHasher;
use App\Domains\Accounts\Contracts\TemporaryPasswordGenerator;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Domains\Accounts\ValueObjects\PasswordStatus;
use App\Domains\Accounts\ValueObjects\SocialProvider;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Accounts\InvitationFixtures;

beforeEach(function () {
    $this->accounts = Mockery::mock(AccountRepository::class);
    $this->temporaryPasswords = Mockery::mock(TemporaryPasswordGenerator::class);
    $this->hasher = Mockery::mock(PasswordHasher::class);

    $this->useCase = new IssueTemporaryPassword($this->accounts, $this->temporaryPasswords, $this->hasher);

    $this->input = new IssueTemporaryPasswordInput(InvitationFixtures::EXISTING_ACCOUNT_ID);

    $this->issuesNothing = function (): void {
        $this->temporaryPasswords->shouldNotReceive('generate');
        $this->hasher->shouldNotReceive('hash');
        $this->accounts->shouldNotReceive('save');
    };
});

describe('an account that may be issued a temporary password', function () {
    beforeEach(function () {
        $this->temporaryPasswords->shouldReceive('generate')->once()->andReturn(InvitationFixtures::temporaryPassword());
        $this->hasher->shouldReceive('hash')->once()
            ->with(InvitationFixtures::TEMPORARY_PASSWORD)
            ->andReturn(InvitationFixtures::TEMPORARY_PASSWORD_HASH);
    });

    it('hands back the plaintext temporary password', function (PasswordStatus $status) {
        $this->accounts->shouldReceive('findById')->once()->with(InvitationFixtures::EXISTING_ACCOUNT_ID)
            ->andReturn(InvitationFixtures::storedAccount($status));
        $this->accounts->shouldReceive('save')->once();

        $issued = $this->useCase->handle($this->input)->value();

        expect($issued)->toBeInstanceOf(IssuedTemporaryPasswordData::class)
            ->and($issued->temporaryPassword)->toBe(InvitationFixtures::TEMPORARY_PASSWORD);
    })->with([
        'with no password yet' => PasswordStatus::Absent,
        'with a temporary password never changed' => PasswordStatus::Temporary,
    ]);

    it('saves the same account holding only the hash, flagged to change it', function (PasswordStatus $status) {
        $this->accounts->shouldReceive('findById')->once()->andReturn(InvitationFixtures::storedAccount($status));
        $saved = null;
        $this->accounts->shouldReceive('save')->once()->with(Mockery::capture($saved));

        $this->useCase->handle($this->input);

        expect($saved)->toBeInstanceOf(Account::class)
            ->and($saved->id)->toBe(InvitationFixtures::EXISTING_ACCOUNT_ID)
            ->and($saved->email())->toBe(InvitationFixtures::EMAIL)
            ->and($saved->mustChangePassword())->toBeTrue()
            ->and($saved->issuedPasswordHash())->toBe(InvitationFixtures::TEMPORARY_PASSWORD_HASH);
    })->with([
        'with no password yet' => PasswordStatus::Absent,
        'with a temporary password never changed' => PasswordStatus::Temporary,
    ]);
});

it('issues nothing to an account holding a password it chose, answering with no password', function () {
    $this->accounts->shouldReceive('findById')->once()->with(InvitationFixtures::EXISTING_ACCOUNT_ID)
        ->andReturn(InvitationFixtures::storedAccount(PasswordStatus::Chosen));
    ($this->issuesNothing)();

    $response = $this->useCase->handle($this->input);

    expect($response->succeeded())->toBeTrue()
        ->and($response->value())->toBeInstanceOf(IssuedTemporaryPasswordData::class)
        ->and($response->value()->temporaryPassword)->toBeNull();
});

it('issues nothing to an account that signs in with Google, answering with no password', function (PasswordStatus $status) {
    $this->accounts->shouldReceive('findById')->once()->with(InvitationFixtures::EXISTING_ACCOUNT_ID)
        ->andReturn(InvitationFixtures::storedAccount($status, [SocialProvider::Google]));
    ($this->issuesNothing)();

    $response = $this->useCase->handle($this->input);

    expect($response->succeeded())->toBeTrue()
        ->and($response->value())->toBeInstanceOf(IssuedTemporaryPasswordData::class)
        ->and($response->value()->temporaryPassword)->toBeNull();
})->with([
    'with no password' => PasswordStatus::Absent,
    'with a temporary password never changed' => PasswordStatus::Temporary,
    'with a password it chose' => PasswordStatus::Chosen,
]);

it('answers with not found, issuing nothing, when the account does not exist', function () {
    $missing = AccountNotFound::withId(InvitationFixtures::EXISTING_ACCOUNT_ID);
    $this->accounts->shouldReceive('findById')->once()->andThrow($missing);
    ($this->issuesNothing)();

    $response = $this->useCase->handle($this->input);

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('account_not_found')
        ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
        ->and($response->error()->cause())->toBe($missing);
});

it('answers a malformed id with not found before asking the repository', function (string $accountId) {
    $this->accounts->shouldNotReceive('findById');
    ($this->issuesNothing)();

    $response = $this->useCase->handle(new IssueTemporaryPasswordInput($accountId));

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('account_not_found')
        ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
})->with([
    'empty' => '',
    'an internal integer key' => '7',
    'not a uuid' => 'account-uuid',
]);

it('lets a programmer error escape rather than dressing it as a domain failure', function () {
    $bug = new RuntimeException('the users table is gone');
    $this->accounts->shouldReceive('findById')->once()->andReturn(InvitationFixtures::storedAccount());
    $this->temporaryPasswords->shouldReceive('generate')->once()->andReturn(InvitationFixtures::temporaryPassword());
    $this->hasher->shouldReceive('hash')->once()->andReturn(InvitationFixtures::TEMPORARY_PASSWORD_HASH);
    $this->accounts->shouldReceive('save')->once()->andThrow($bug);

    expect(fn () => $this->useCase->handle($this->input))->toThrow($bug);
});
