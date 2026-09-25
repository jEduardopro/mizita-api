<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\DeleteAccountInput;
use App\Domains\Accounts\Exceptions\AccountDeletionEmailMismatch;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Domains\Accounts\Exceptions\IncorrectAccountPassword;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\Accounts\AccountDeletionFixtures;

describe('fromRequest', function () {
    it('reads the password and the email off the payload', function () {
        $input = DeleteAccountInput::fromRequest(
            ['password' => 'Correct1!Pass', 'email' => 'ada@example.com'],
            AccountDeletionFixtures::ACCOUNT_ID,
        );

        expect($input->accountId)->toBe(AccountDeletionFixtures::ACCOUNT_ID)
            ->and($input->password)->toBe('Correct1!Pass')
            ->and($input->email)->toBe('ada@example.com');
    });

    it('takes the account from the authenticated caller, never from the body', function () {
        $input = DeleteAccountInput::fromRequest(
            ['account_id' => '01930000-0000-7000-8000-00000000ffff', 'accountId' => '01930000-0000-7000-8000-00000000fffe'],
            AccountDeletionFixtures::ACCOUNT_ID,
        );

        expect($input->accountId)->toBe(AccountDeletionFixtures::ACCOUNT_ID);
    });

    it('survives a payload with every key missing, leaving both confirmations null', function () {
        $input = DeleteAccountInput::fromRequest([], AccountDeletionFixtures::ACCOUNT_ID);

        expect($input->password)->toBeNull()
            ->and($input->email)->toBeNull()
            ->and(fn () => $input->validate())->not->toThrow(Throwable::class);
    });

    it('drops a confirmation that is not a string instead of failing on it', function (mixed $value) {
        $input = DeleteAccountInput::fromRequest(['password' => $value, 'email' => $value], AccountDeletionFixtures::ACCOUNT_ID);

        expect($input->password)->toBeNull()
            ->and($input->email)->toBeNull();
    })->with([
        'null' => null,
        'an integer' => 12345678,
        'a boolean' => true,
        'an array' => [['ada@example.com']],
    ]);

    it('keeps the confirmations exactly as typed, leaving normalization to the account', function () {
        $input = DeleteAccountInput::fromRequest(
            ['password' => '  spaced pässword  ', 'email' => '  Ada@Example.COM '],
            AccountDeletionFixtures::ACCOUNT_ID,
        );

        expect($input->password)->toBe('  spaced pässword  ')
            ->and($input->email)->toBe('  Ada@Example.COM ');
    });
});

describe('validate', function () {
    it('accepts a well formed confirmation', function (?string $password, ?string $email) {
        expect(fn () => (new DeleteAccountInput(AccountDeletionFixtures::ACCOUNT_ID, $password, $email))->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'a password' => ['Correct1!Pass', null],
        'an email' => [null, 'ada@example.com'],
        'both' => ['Correct1!Pass', 'ada@example.com'],
        'neither' => [null, null],
        'empty strings, left for the account to refuse' => ['', ''],
        'a password at the limit' => [str_repeat('a', DeleteAccountInput::MAXIMUM_CONFIRMATION_LENGTH), null],
        'a password at the limit counted in characters' => [str_repeat('ñ', DeleteAccountInput::MAXIMUM_CONFIRMATION_LENGTH), null],
        'an email at the limit' => [null, str_repeat('a', DeleteAccountInput::MAXIMUM_CONFIRMATION_LENGTH)],
    ]);

    it('accepts an upper case account uuid', function () {
        expect(fn () => (new DeleteAccountInput('01930000-0000-7000-8000-00000000AC01', 'x', null))->validate())
            ->not->toThrow(Throwable::class);
    });

    it('refuses a payload the form request would have refused', function (string $accountId, ?string $password, ?string $email, string $exception) {
        $input = new DeleteAccountInput($accountId, $password, $email);

        expect(fn () => $input->validate())->toThrow($exception);
    })->with([
        'an empty account id' => ['', 'Correct1!Pass', null, AccountNotFound::class],
        'an account id that is not a uuid' => ['account-uuid', 'Correct1!Pass', null, AccountNotFound::class],
        'an internal integer key' => ['7', 'Correct1!Pass', null, AccountNotFound::class],
        'a uuid with a trailing newline' => [AccountDeletionFixtures::ACCOUNT_ID."\n", 'Correct1!Pass', null, AccountNotFound::class],
        'a password past the limit' => [AccountDeletionFixtures::ACCOUNT_ID, str_repeat('a', DeleteAccountInput::MAXIMUM_CONFIRMATION_LENGTH + 1), null, IncorrectAccountPassword::class],
        'an email past the limit' => [AccountDeletionFixtures::ACCOUNT_ID, null, str_repeat('a', DeleteAccountInput::MAXIMUM_CONFIRMATION_LENGTH + 1), AccountDeletionEmailMismatch::class],
    ]);

    it('refuses with a domain failure every time', function (DeleteAccountInput $input) {
        $refusal = null;

        try {
            $input->validate();
        } catch (Throwable $caught) {
            $refusal = $caught;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class);
    })->with([
        'a malformed account id' => fn () => new DeleteAccountInput('nope', null, null),
        'a password past the limit' => fn () => new DeleteAccountInput(AccountDeletionFixtures::ACCOUNT_ID, str_repeat('a', 256), null),
        'an email past the limit' => fn () => new DeleteAccountInput(AccountDeletionFixtures::ACCOUNT_ID, null, str_repeat('a', 256)),
    ]);

    it('checks the account id before the confirmations', function () {
        expect(fn () => (new DeleteAccountInput('nope', str_repeat('a', 256), str_repeat('a', 256)))->validate())
            ->toThrow(AccountNotFound::class);
    });

    it('checks the password before the email', function () {
        expect(fn () => (new DeleteAccountInput(AccountDeletionFixtures::ACCOUNT_ID, str_repeat('a', 256), str_repeat('a', 256)))->validate())
            ->toThrow(IncorrectAccountPassword::class);
    });
});
