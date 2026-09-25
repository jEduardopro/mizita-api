<?php

declare(strict_types=1);

use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\AccountAlreadyScheduledForDeletion;
use App\Domains\Accounts\Exceptions\AccountDeletionEmailMismatch;
use App\Domains\Accounts\Exceptions\AccountHoldsOwnPassword;
use App\Domains\Accounts\Exceptions\AccountNotScheduledForDeletion;
use App\Domains\Accounts\Exceptions\AccountPendingReactivation;
use App\Domains\Accounts\Exceptions\AccountSignsInWithSocialIdentity;
use App\Domains\Accounts\Exceptions\InvalidAccountEmail;
use App\Domains\Accounts\Exceptions\InvalidAccountName;
use App\Domains\Accounts\ValueObjects\DeletionGracePeriod;
use App\Domains\Accounts\ValueObjects\PasswordStatus;
use App\Domains\Accounts\ValueObjects\SocialProvider;
use App\Domains\Accounts\ValueObjects\TwoFactorStatus;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Accounts\AccountDeletionFixtures;

describe('registerWithVerifiedEmail', function () {
    it('registers an account already verified at the given instant', function () {
        $now = new DateTimeImmutable('2026-01-01T12:00:00+00:00');

        $account = Account::registerWithVerifiedEmail(
            id: 'account-uuid',
            name: 'Ada Lovelace',
            email: 'ada@example.com',
            now: $now,
        );

        expect($account->id)->toBe('account-uuid')
            ->and($account->name())->toBe('Ada Lovelace')
            ->and($account->email())->toBe('ada@example.com')
            ->and($account->emailVerifiedAt())->toEqual($now)
            ->and($account->createdAt)->toEqual($now);
    });

    it('trims the surrounding whitespace off the name', function () {
        $account = Account::registerWithVerifiedEmail(
            'account-uuid',
            "  Ada Lovelace\n",
            'ada@example.com',
            new DateTimeImmutable,
        );

        expect($account->name())->toBe('Ada Lovelace');
    });

    it('keeps accents and non-latin characters in the name intact', function (string $name) {
        $account = Account::registerWithVerifiedEmail(
            'account-uuid',
            $name,
            'ada@example.com',
            new DateTimeImmutable,
        );

        expect($account->name())->toBe($name);
    })->with([
        'accents' => 'José Álvarez Muñoz',
        'cyrillic' => 'Ада Лавлейс',
        'cjk' => '愛田 明日香',
        'emoji' => 'Ada 👩‍💻',
    ]);

    it('rejects a blank name', function (string $name) {
        expect(fn () => Account::registerWithVerifiedEmail('account-uuid', $name, 'ada@example.com', new DateTimeImmutable))
            ->toThrow(InvalidAccountName::class, 'An account name cannot be empty.');
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'newline' => "\n",
        'mixed whitespace' => " \t\n ",
    ]);

    it('checks the name before the email, reporting the first thing that is wrong', function () {
        expect(fn () => Account::registerWithVerifiedEmail('account-uuid', '', 'not-an-email', new DateTimeImmutable))
            ->toThrow(InvalidAccountName::class);
    });

    it('normalizes the email to lowercase so one address is one account', function () {
        $account = Account::registerWithVerifiedEmail(
            'account-uuid',
            'Ada',
            '  Ada@Example.COM  ',
            new DateTimeImmutable,
        );

        expect($account->email())->toBe('ada@example.com');
    });

    it('rejects a blank email', function (string $email) {
        expect(fn () => Account::registerWithVerifiedEmail('account-uuid', 'Ada', $email, new DateTimeImmutable))
            ->toThrow(InvalidAccountEmail::class, 'An account email cannot be empty.');
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'newline' => "\n",
    ]);

    it('rejects a malformed email, naming the offending address', function (string $email) {
        expect(fn () => Account::registerWithVerifiedEmail('account-uuid', 'Ada', $email, new DateTimeImmutable))
            ->toThrow(InvalidAccountEmail::class, sprintf('[%s] is not a valid email address.', mb_strtolower(trim($email))));
    })->with([
        'no at sign' => 'ada.example.com',
        'no domain' => 'ada@',
        'no local part' => '@example.com',
        'spaces inside' => 'ada lovelace@example.com',
        'double at' => 'ada@@example.com',
    ]);

    it('reports the malformed address in lowercase, matching what it would have stored', function () {
        expect(fn () => Account::registerWithVerifiedEmail('account-uuid', 'Ada', 'ADA.EXAMPLE.COM', new DateTimeImmutable))
            ->toThrow(InvalidAccountEmail::class, '[ada.example.com] is not a valid email address.');
    });
});

describe('restore', function () {
    it('rehydrates an account exactly as it was stored', function () {
        $verifiedAt = new DateTimeImmutable('2025-06-01T08:30:00+00:00');
        $createdAt = new DateTimeImmutable('2025-05-01T08:30:00+00:00');

        $account = Account::restore('account-uuid', 'Ada Lovelace', 'ada@example.com', $verifiedAt, $createdAt);

        expect($account->id)->toBe('account-uuid')
            ->and($account->name())->toBe('Ada Lovelace')
            ->and($account->email())->toBe('ada@example.com')
            ->and($account->emailVerifiedAt())->toEqual($verifiedAt)
            ->and($account->createdAt)->toEqual($createdAt);
    });

    it('rehydrates an account whose email is still unverified', function () {
        $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable);

        expect($account->emailVerifiedAt())->toBeNull();
    });

    it('skips the creation-time email rules, because storage is not a second validation gate', function (string $email) {
        $account = Account::restore('account-uuid', 'Ada', $email, null, new DateTimeImmutable);

        expect($account->email())->toBe($email);
    })->with([
        'empty' => '',
        'malformed' => 'legacy-address',
        'still uppercase' => 'ADA@EXAMPLE.COM',
    ]);

    it('skips the creation-time name rule too, so a row written before it still loads', function (string $name) {
        $account = Account::restore('account-uuid', $name, 'ada@example.com', null, new DateTimeImmutable);

        expect($account->name())->toBe($name);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'untrimmed' => '  Ada Lovelace  ',
    ]);
});

describe('verifyEmail', function () {
    it('records the instant control of the address was proven', function () {
        $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable);
        $verifiedAt = new DateTimeImmutable('2026-03-29T03:30:00+02:00');

        $account->verifyEmail($verifiedAt);

        expect($account->emailVerifiedAt())->toEqual($verifiedAt);
    });

    it('keeps the first timestamp when the email was already verified', function () {
        $originalVerification = new DateTimeImmutable('2025-01-01T00:00:00+00:00');
        $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', $originalVerification, new DateTimeImmutable);

        $account->verifyEmail(new DateTimeImmutable('2026-01-01T12:00:00+00:00'));

        expect($account->emailVerifiedAt())->toEqual($originalVerification);
    });

    it('leaves the rest of the account untouched', function () {
        $createdAt = new DateTimeImmutable('2025-05-01T08:30:00+00:00');
        $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, $createdAt);

        $account->verifyEmail(new DateTimeImmutable('2026-01-01T12:00:00+00:00'));

        expect($account->id)->toBe('account-uuid')
            ->and($account->name())->toBe('Ada')
            ->and($account->email())->toBe('ada@example.com')
            ->and($account->createdAt)->toEqual($createdAt);
    });
});

describe('the name length limit', function () {
    it('accepts a name at exactly the limit, counting characters rather than bytes', function (string $character) {
        $name = str_repeat($character, Account::MAXIMUM_NAME_LENGTH);

        $account = Account::registerWithVerifiedEmail('account-uuid', $name, 'ada@example.com', new DateTimeImmutable);

        expect($account->name())->toBe($name);
    })->with([
        'ascii' => 'a',
        'accented' => 'ñ',
        'cjk' => '愛',
    ]);

    it('rejects a name one character past the limit, naming the limit', function () {
        expect(fn () => Account::registerWithVerifiedEmail(
            'account-uuid',
            str_repeat('a', Account::MAXIMUM_NAME_LENGTH + 1),
            'ada@example.com',
            new DateTimeImmutable,
        ))->toThrow(InvalidAccountName::class, 'An account name takes up to [255] characters.');
    });

    it('measures the name after trimming it', function () {
        $name = '   '.str_repeat('a', Account::MAXIMUM_NAME_LENGTH).'   ';

        $account = Account::registerWithVerifiedEmail('account-uuid', $name, 'ada@example.com', new DateTimeImmutable);

        expect($account->name())->toBe(str_repeat('a', Account::MAXIMUM_NAME_LENGTH));
    });

    it('lets a stored name longer than the limit still load', function () {
        $name = str_repeat('a', Account::MAXIMUM_NAME_LENGTH + 10);

        expect(Account::restore('account-uuid', $name, 'ada@example.com', null, new DateTimeImmutable)->name())
            ->toBe($name);
    });
});

describe('rename', function () {
    beforeEach(function () {
        $this->createdAt = new DateTimeImmutable('2025-05-01T08:30:00+00:00');
        $this->verifiedAt = new DateTimeImmutable('2025-06-01T08:30:00+00:00');
        $this->account = Account::restore('account-uuid', 'Ada Lovelace', 'ada@example.com', $this->verifiedAt, $this->createdAt);
    });

    it('takes the new name, trimmed', function () {
        $this->account->rename("  Ada King\n");

        expect($this->account->name())->toBe('Ada King');
    });

    it('keeps accents and non-latin characters intact', function (string $name) {
        $this->account->rename($name);

        expect($this->account->name())->toBe($name);
    })->with([
        'accents' => 'José Álvarez Muñoz',
        'cyrillic' => 'Ада Лавлейс',
        'cjk' => '愛田 明日香',
    ]);

    it('leaves everything but the name untouched', function () {
        $this->account->rename('Ada King');

        expect($this->account->id)->toBe('account-uuid')
            ->and($this->account->email())->toBe('ada@example.com')
            ->and($this->account->emailVerifiedAt())->toEqual($this->verifiedAt)
            ->and($this->account->createdAt)->toEqual($this->createdAt);
    });

    it('refuses a blank name and keeps the one it had', function (string $name) {
        expect(fn () => $this->account->rename($name))
            ->toThrow(InvalidAccountName::class, 'An account name cannot be empty.')
            ->and($this->account->name())->toBe('Ada Lovelace');
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
    ]);

    it('refuses a name past the limit and keeps the one it had', function () {
        expect(fn () => $this->account->rename(str_repeat('a', Account::MAXIMUM_NAME_LENGTH + 1)))
            ->toThrow(InvalidAccountName::class, 'An account name takes up to [255] characters.')
            ->and($this->account->name())->toBe('Ada Lovelace');
    });

    it('accepts a name at exactly the limit', function () {
        $this->account->rename(str_repeat('ñ', Account::MAXIMUM_NAME_LENGTH));

        expect(mb_strlen($this->account->name()))->toBe(Account::MAXIMUM_NAME_LENGTH);
    });

    it('holds the name rule a restored row skipped, the moment the name is changed', function () {
        $legacy = Account::restore('account-uuid', '', 'ada@example.com', null, new DateTimeImmutable);

        expect(fn () => $legacy->rename(' '))->toThrow(InvalidAccountName::class);
    });
});

describe('inviteWithTemporaryPassword', function () {
    it('invites an unverified account that must replace the password it was issued', function () {
        $now = new DateTimeImmutable('2026-01-01T12:00:00+00:00');

        $account = Account::inviteWithTemporaryPassword(
            id: 'account-uuid',
            name: 'Ada Lovelace',
            email: 'ada@example.com',
            temporaryPasswordHash: 'temporary-hash',
            now: $now,
        );

        expect($account->id)->toBe('account-uuid')
            ->and($account->name())->toBe('Ada Lovelace')
            ->and($account->email())->toBe('ada@example.com')
            ->and($account->emailVerifiedAt())->toBeNull()
            ->and($account->createdAt)->toEqual($now)
            ->and($account->mustChangePassword())->toBeTrue()
            ->and($account->issuedPasswordHash())->toBe('temporary-hash');
    });

    it('still accepts a fresh temporary password while the first one is unchanged', function () {
        $account = Account::inviteWithTemporaryPassword('account-uuid', 'Ada', 'ada@example.com', 'temporary-hash', new DateTimeImmutable);

        expect($account->acceptsTemporaryPassword())->toBeTrue();
    });

    it('trims the name and lowercases the email', function () {
        $account = Account::inviteWithTemporaryPassword('account-uuid', "  Ada Lovelace\n", '  Ada@Example.COM ', 'temporary-hash', new DateTimeImmutable);

        expect($account->name())->toBe('Ada Lovelace')
            ->and($account->email())->toBe('ada@example.com');
    });

    it('holds the same name and email rules as a registration', function (string $name, string $email, string $exception) {
        expect(fn () => Account::inviteWithTemporaryPassword('account-uuid', $name, $email, 'temporary-hash', new DateTimeImmutable))
            ->toThrow($exception);
    })->with([
        'blank name' => ['   ', 'ada@example.com', InvalidAccountName::class],
        'name past the limit' => [str_repeat('a', Account::MAXIMUM_NAME_LENGTH + 1), 'ada@example.com', InvalidAccountName::class],
        'blank email' => ['Ada', ' ', InvalidAccountEmail::class],
        'malformed email' => ['Ada', 'ada.example.com', InvalidAccountEmail::class],
    ]);
});

describe('inviteWithoutPassword', function () {
    it('invites an unverified account with no password and nothing to change', function () {
        $now = new DateTimeImmutable('2026-01-01T12:00:00+00:00');

        $account = Account::inviteWithoutPassword(
            id: 'account-uuid',
            name: 'Ada Lovelace',
            email: 'ada@example.com',
            now: $now,
        );

        expect($account->id)->toBe('account-uuid')
            ->and($account->name())->toBe('Ada Lovelace')
            ->and($account->email())->toBe('ada@example.com')
            ->and($account->emailVerifiedAt())->toBeNull()
            ->and($account->createdAt)->toEqual($now)
            ->and($account->mustChangePassword())->toBeFalse()
            ->and($account->issuedPasswordHash())->toBeNull();
    });

    it('can be issued a temporary password later', function () {
        expect(Account::inviteWithoutPassword('account-uuid', 'Ada', 'ada@example.com', new DateTimeImmutable)->acceptsTemporaryPassword())
            ->toBeTrue();
    });

    it('trims the name and lowercases the email', function () {
        $account = Account::inviteWithoutPassword('account-uuid', '  José Álvarez  ', ' JOSE@Example.com ', new DateTimeImmutable);

        expect($account->name())->toBe('José Álvarez')
            ->and($account->email())->toBe('jose@example.com');
    });

    it('holds the same name and email rules as a registration', function (string $name, string $email, string $exception) {
        expect(fn () => Account::inviteWithoutPassword('account-uuid', $name, $email, new DateTimeImmutable))
            ->toThrow($exception);
    })->with([
        'blank name' => ["\t", 'ada@example.com', InvalidAccountName::class],
        'blank email' => ['Ada', '', InvalidAccountEmail::class],
        'malformed email' => ['Ada', 'ada@@example.com', InvalidAccountEmail::class],
    ]);
});

describe('the password status of a registered or restored account', function () {
    it('registers a verified account with no password and nothing to change', function () {
        $account = Account::registerWithVerifiedEmail('account-uuid', 'Ada', 'ada@example.com', new DateTimeImmutable);

        expect($account->mustChangePassword())->toBeFalse()
            ->and($account->issuedPasswordHash())->toBeNull()
            ->and($account->acceptsTemporaryPassword())->toBeTrue();
    });

    it('restores an account with no password when no status is given', function () {
        $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable);

        expect($account->mustChangePassword())->toBeFalse()
            ->and($account->acceptsTemporaryPassword())->toBeTrue();
    });

    it('restores the stored status', function (PasswordStatus $status, bool $mustChange, bool $acceptsTemporary) {
        $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable, $status);

        expect($account->mustChangePassword())->toBe($mustChange)
            ->and($account->acceptsTemporaryPassword())->toBe($acceptsTemporary);
    })->with([
        'no password' => [PasswordStatus::Absent, false, true],
        'a temporary password' => [PasswordStatus::Temporary, true, true],
        'a chosen password' => [PasswordStatus::Chosen, false, false],
    ]);

    it('carries no issued hash out of storage, so nothing is rewritten on the next save', function (PasswordStatus $status) {
        $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable, $status);

        expect($account->issuedPasswordHash())->toBeNull();
    })->with([PasswordStatus::Absent, PasswordStatus::Temporary, PasswordStatus::Chosen]);
});

describe('issueTemporaryPassword', function () {
    it('issues a temporary password to an account that has none', function () {
        $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable, PasswordStatus::Absent);

        $account->issueTemporaryPassword('temporary-hash');

        expect($account->mustChangePassword())->toBeTrue()
            ->and($account->issuedPasswordHash())->toBe('temporary-hash');
    });

    it('replaces a temporary password the person never changed', function () {
        $account = Account::inviteWithTemporaryPassword('account-uuid', 'Ada', 'ada@example.com', 'first-hash', new DateTimeImmutable);

        $account->issueTemporaryPassword('second-hash');

        expect($account->mustChangePassword())->toBeTrue()
            ->and($account->issuedPasswordHash())->toBe('second-hash');
    });

    it('refuses to overwrite a password the person chose', function () {
        $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable, PasswordStatus::Chosen);

        expect(fn () => $account->issueTemporaryPassword('temporary-hash'))
            ->toThrow(AccountHoldsOwnPassword::class, 'Account [account-uuid] already holds a password of its own.');
    });

    it('leaves a chosen password in place when it refuses', function () {
        $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable, PasswordStatus::Chosen);

        expect(fn () => $account->issueTemporaryPassword('temporary-hash'))->toThrow(AccountHoldsOwnPassword::class)
            ->and($account->mustChangePassword())->toBeFalse()
            ->and($account->issuedPasswordHash())->toBeNull()
            ->and($account->acceptsTemporaryPassword())->toBeFalse();
    });

    it('classifies the refusal as a conflict with a stable code', function () {
        $refusal = AccountHoldsOwnPassword::forAccount('account-uuid');

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal->errorCode())->toBe('account_holds_own_password')
            ->and($refusal->kind())->toBe(DomainFailureKind::Conflict);
    });

    it('leaves the identity, the name, the email and the timestamps untouched', function () {
        $verifiedAt = new DateTimeImmutable('2025-06-01T08:30:00+00:00');
        $createdAt = new DateTimeImmutable('2025-05-01T08:30:00+00:00');
        $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', $verifiedAt, $createdAt);

        $account->issueTemporaryPassword('temporary-hash');

        expect($account->id)->toBe('account-uuid')
            ->and($account->name())->toBe('Ada')
            ->and($account->email())->toBe('ada@example.com')
            ->and($account->emailVerifiedAt())->toEqual($verifiedAt)
            ->and($account->createdAt)->toEqual($createdAt);
    });
});

describe('an account that signs in with a linked social identity', function () {
    beforeEach(function () {
        $this->linkedAccount = fn (PasswordStatus $status) => Account::restore(
            'account-uuid',
            'Ada',
            'ada@example.com',
            null,
            new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
            $status,
            [SocialProvider::Google],
        );
    });

    it('accepts no temporary password, whatever its password status', function (PasswordStatus $status) {
        expect(($this->linkedAccount)($status)->acceptsTemporaryPassword())->toBeFalse();
    })->with([
        'no password' => PasswordStatus::Absent,
        'a temporary password' => PasswordStatus::Temporary,
        'a chosen password' => PasswordStatus::Chosen,
    ]);

    it('refuses a temporary password because it signs in through the identity', function (PasswordStatus $status) {
        expect(fn () => ($this->linkedAccount)($status)->issueTemporaryPassword('temporary-hash'))
            ->toThrow(AccountSignsInWithSocialIdentity::class, 'Account [account-uuid] signs in through a linked social identity.');
    })->with([
        'no password' => PasswordStatus::Absent,
        'a temporary password' => PasswordStatus::Temporary,
    ]);

    it('changes nothing when it refuses', function (PasswordStatus $status, bool $mustChange) {
        $account = ($this->linkedAccount)($status);

        expect(fn () => $account->issueTemporaryPassword('temporary-hash'))->toThrow(AccountSignsInWithSocialIdentity::class)
            ->and($account->mustChangePassword())->toBe($mustChange)
            ->and($account->issuedPasswordHash())->toBeNull()
            ->and($account->acceptsTemporaryPassword())->toBeFalse();
    })->with([
        'no password' => [PasswordStatus::Absent, false],
        'a temporary password' => [PasswordStatus::Temporary, true],
    ]);

    it('reports the chosen password first when the account holds one as well', function () {
        expect(fn () => ($this->linkedAccount)(PasswordStatus::Chosen)->issueTemporaryPassword('temporary-hash'))
            ->toThrow(AccountHoldsOwnPassword::class, 'Account [account-uuid] already holds a password of its own.');
    });

    it('classifies the refusal as a conflict with a stable code', function () {
        $refusal = AccountSignsInWithSocialIdentity::forAccount('account-uuid');

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal->errorCode())->toBe('account_signs_in_with_social_identity')
            ->and($refusal->kind())->toBe(DomainFailureKind::Conflict);
    });
});

describe('an account that was never asked to be deleted', function () {
    it('is not scheduled for deletion and has no grace period, however it was created', function (Account $account) {
        expect($account->isScheduledForDeletion())->toBeFalse()
            ->and($account->deletionRequestedAt())->toBeNull()
            ->and($account->gracePeriodEndsAt())->toBeNull();
    })->with([
        'registered through Google' => fn () => Account::registerWithVerifiedEmail('account-uuid', 'Ada', 'ada@example.com', new DateTimeImmutable),
        'invited with a temporary password' => fn () => Account::inviteWithTemporaryPassword('account-uuid', 'Ada', 'ada@example.com', 'temporary-hash', new DateTimeImmutable),
        'invited without a password' => fn () => Account::inviteWithoutPassword('account-uuid', 'Ada', 'ada@example.com', new DateTimeImmutable),
        'restored with no deletion request' => fn () => Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable),
    ]);
});

describe('scheduleDeletion', function () {
    it('records the instant the deletion was requested', function () {
        $account = AccountDeletionFixtures::activeAccount();

        $account->scheduleDeletion(AccountDeletionFixtures::now());

        expect($account->isScheduledForDeletion())->toBeTrue()
            ->and($account->deletionRequestedAt())->toEqual(AccountDeletionFixtures::now());
    });

    it('ends the grace period exactly thirty days after the request', function () {
        $account = AccountDeletionFixtures::activeAccount();

        $account->scheduleDeletion(AccountDeletionFixtures::now());

        expect($account->gracePeriodEndsAt()->format(DATE_ATOM))->toBe(AccountDeletionFixtures::GRACE_PERIOD_ENDS_AT)
            ->and($account->gracePeriodEndsAt()->getTimestamp() - AccountDeletionFixtures::now()->getTimestamp())
            ->toBe(DeletionGracePeriod::DAYS * 86400);
    });

    it('leaves the identity, the name, the email and the password status untouched', function () {
        $account = AccountDeletionFixtures::activeAccount(PasswordStatus::Chosen);

        $account->scheduleDeletion(AccountDeletionFixtures::now());

        expect($account->id)->toBe(AccountDeletionFixtures::ACCOUNT_ID)
            ->and($account->name())->toBe(AccountDeletionFixtures::NAME)
            ->and($account->email())->toBe(AccountDeletionFixtures::EMAIL)
            ->and($account->holdsPassword())->toBeTrue();
    });

    it('refuses to schedule an account twice', function () {
        $account = AccountDeletionFixtures::scheduledAccount();

        expect(fn () => $account->scheduleDeletion(AccountDeletionFixtures::now()))
            ->toThrow(AccountAlreadyScheduledForDeletion::class, 'Account [01930000-0000-7000-8000-00000000ac01] is already scheduled for deletion.');
    });

    it('keeps the first request, and so the first grace period, when it refuses', function () {
        $account = AccountDeletionFixtures::scheduledAccount();

        expect(fn () => $account->scheduleDeletion(AccountDeletionFixtures::now()))->toThrow(AccountAlreadyScheduledForDeletion::class)
            ->and($account->deletionRequestedAt()->format(DATE_ATOM))->toBe(AccountDeletionFixtures::DELETION_REQUESTED_AT)
            ->and($account->gracePeriodEndsAt()->format(DATE_ATOM))->toBe(AccountDeletionFixtures::REQUESTED_GRACE_PERIOD_ENDS_AT);
    });

    it('classifies the double request as a conflict with a stable code', function () {
        $refusal = AccountAlreadyScheduledForDeletion::forAccount('account-uuid');

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal->errorCode())->toBe('account_already_scheduled_for_deletion')
            ->and($refusal->kind())->toBe(DomainFailureKind::Conflict);
    });
});

describe('restoring an account scheduled for deletion', function () {
    it('rehydrates the stored request and derives the grace period from it', function () {
        $account = AccountDeletionFixtures::scheduledAccount();

        expect($account->isScheduledForDeletion())->toBeTrue()
            ->and($account->deletionRequestedAt()->format(DATE_ATOM))->toBe(AccountDeletionFixtures::DELETION_REQUESTED_AT)
            ->and($account->gracePeriodEndsAt()->format(DATE_ATOM))->toBe(AccountDeletionFixtures::REQUESTED_GRACE_PERIOD_ENDS_AT);
    });

    it('rehydrates a request whose grace period has already run out, because purging is not its job', function () {
        $account = AccountDeletionFixtures::scheduledAccount('2024-01-01T00:00:00+00:00');

        expect($account->isScheduledForDeletion())->toBeTrue()
            ->and($account->gracePeriodEndsAt()->format(DATE_ATOM))->toBe('2024-01-31T00:00:00+00:00');
    });
});

describe('reactivate', function () {
    it('clears the deletion request and the grace period', function () {
        $account = AccountDeletionFixtures::scheduledAccount();

        $account->reactivate();

        expect($account->isScheduledForDeletion())->toBeFalse()
            ->and($account->deletionRequestedAt())->toBeNull()
            ->and($account->gracePeriodEndsAt())->toBeNull();
    });

    it('can be scheduled for deletion again once reactivated', function () {
        $account = AccountDeletionFixtures::scheduledAccount();
        $account->reactivate();

        $account->scheduleDeletion(AccountDeletionFixtures::now());

        expect($account->deletionRequestedAt())->toEqual(AccountDeletionFixtures::now());
    });

    it('refuses to reactivate an account that is not scheduled for deletion', function () {
        $account = AccountDeletionFixtures::activeAccount();

        expect(fn () => $account->reactivate())
            ->toThrow(AccountNotScheduledForDeletion::class, 'Account [01930000-0000-7000-8000-00000000ac01] is not scheduled for deletion.');
    });

    it('classifies the refusal as a conflict with a stable code', function () {
        $refusal = AccountNotScheduledForDeletion::forAccount('account-uuid');

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal->errorCode())->toBe('account_not_scheduled_for_deletion')
            ->and($refusal->kind())->toBe(DomainFailureKind::Conflict);
    });
});

describe('ensureActive', function () {
    it('lets an active account through', function () {
        expect(fn () => AccountDeletionFixtures::activeAccount()->ensureActive())->not->toThrow(Throwable::class);
    });

    it('stops an account scheduled for deletion, naming it', function () {
        $refusal = null;

        try {
            AccountDeletionFixtures::scheduledAccount()->ensureActive();
        } catch (AccountPendingReactivation $caught) {
            $refusal = $caught;
        }

        expect($refusal)->toBeInstanceOf(AccountPendingReactivation::class)
            ->and($refusal->accountId)->toBe(AccountDeletionFixtures::ACCOUNT_ID)
            ->and($refusal->getMessage())
            ->toBe('Account [01930000-0000-7000-8000-00000000ac01] is scheduled for deletion and must be reactivated before signing in.');
    });

    it('classifies the refusal as a conflict with a stable code', function () {
        $refusal = AccountPendingReactivation::forAccount('account-uuid');

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal->errorCode())->toBe('account_pending_reactivation')
            ->and($refusal->kind())->toBe(DomainFailureKind::Conflict);
    });
});

describe('ensureScheduledForDeletion', function () {
    it('lets an account scheduled for deletion through', function () {
        expect(fn () => AccountDeletionFixtures::scheduledAccount()->ensureScheduledForDeletion())->not->toThrow(Throwable::class);
    });

    it('stops an active account', function () {
        expect(fn () => AccountDeletionFixtures::activeAccount()->ensureScheduledForDeletion())
            ->toThrow(AccountNotScheduledForDeletion::class);
    });
});

describe('holdsPassword', function () {
    it('holds a password whenever one is stored, temporary or chosen', function (PasswordStatus $status, bool $holds) {
        expect(AccountDeletionFixtures::activeAccount($status)->holdsPassword())->toBe($holds);
    })->with([
        'no password' => [PasswordStatus::Absent, false],
        'a temporary password' => [PasswordStatus::Temporary, true],
        'a chosen password' => [PasswordStatus::Chosen, true],
    ]);

    it('holds no password when it signs in only through Google', function () {
        expect(AccountDeletionFixtures::activeAccount(PasswordStatus::Absent, linkedSocialProviders: [SocialProvider::Google])->holdsPassword())
            ->toBeFalse();
    });

    it('holds the password it was invited with', function () {
        expect(Account::inviteWithTemporaryPassword('account-uuid', 'Ada', 'ada@example.com', 'temporary-hash', new DateTimeImmutable)->holdsPassword())
            ->toBeTrue();
    });

    it('holds a temporary password once one is issued', function () {
        $account = AccountDeletionFixtures::activeAccount(PasswordStatus::Absent);

        $account->issueTemporaryPassword('temporary-hash');

        expect($account->holdsPassword())->toBeTrue();
    });
});

describe('ensureDeletionConfirmedBy', function () {
    it('accepts the account email however it was typed', function (string $typed) {
        expect(fn () => AccountDeletionFixtures::activeAccount(PasswordStatus::Absent)->ensureDeletionConfirmedBy($typed))
            ->not->toThrow(Throwable::class);
    })->with([
        'exactly' => 'ada@example.com',
        'upper case' => 'ADA@EXAMPLE.COM',
        'mixed case' => 'Ada@Example.Com',
        'padded' => "  ada@example.com\n",
        'padded and mixed case' => "\tAda@Example.com  ",
    ]);

    it('compares accented addresses without regard to case', function () {
        $account = AccountDeletionFixtures::activeAccount(PasswordStatus::Absent, email: 'josé@example.com');

        expect(fn () => $account->ensureDeletionConfirmedBy('JOSÉ@EXAMPLE.COM'))->not->toThrow(Throwable::class);
    });

    it('accepts a legacy address stored in upper case', function () {
        $account = AccountDeletionFixtures::activeAccount(PasswordStatus::Absent, email: 'ADA@EXAMPLE.COM');

        expect(fn () => $account->ensureDeletionConfirmedBy('ada@example.com'))->not->toThrow(Throwable::class);
    });

    it('refuses anything but the account email, naming the account', function (string $typed) {
        expect(fn () => AccountDeletionFixtures::activeAccount(PasswordStatus::Absent)->ensureDeletionConfirmedBy($typed))
            ->toThrow(AccountDeletionEmailMismatch::class, 'The email typed to confirm the deletion of account [01930000-0000-7000-8000-00000000ac01] does not match it.');
    })->with([
        'empty' => '',
        'whitespace only' => "  \t ",
        'another address' => 'grace@example.com',
        'a truncated address' => 'ada@example.co',
        'a longer address' => 'ada@example.com.mx',
        'a space inside' => 'ada @example.com',
        'the local part only' => 'ada',
    ]);

    it('changes nothing about the account when it refuses', function () {
        $account = AccountDeletionFixtures::activeAccount(PasswordStatus::Absent);

        expect(fn () => $account->ensureDeletionConfirmedBy('grace@example.com'))->toThrow(AccountDeletionEmailMismatch::class)
            ->and($account->isScheduledForDeletion())->toBeFalse()
            ->and($account->email())->toBe(AccountDeletionFixtures::EMAIL);
    });

    it('classifies the mismatch as invalid input with a stable code', function () {
        $refusal = AccountDeletionEmailMismatch::forAccount('account-uuid');

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal->errorCode())->toBe('account_deletion_email_mismatch')
            ->and($refusal->kind())->toBe(DomainFailureKind::Invalid);
    });
});

describe('the two-factor status', function () {
    it('starts disabled however the account is created, so nobody is challenged for a code they never set up', function (Account $account) {
        expect($account->twoFactorStatus())->toBe(TwoFactorStatus::Disabled)
            ->and($account->requiresSecondFactor())->toBeFalse();
    })->with([
        'registered through Google' => fn () => Account::registerWithVerifiedEmail('account-uuid', 'Ada', 'ada@example.com', new DateTimeImmutable),
        'invited with a temporary password' => fn () => Account::inviteWithTemporaryPassword('account-uuid', 'Ada', 'ada@example.com', 'temporary-hash', new DateTimeImmutable),
        'invited without a password' => fn () => Account::inviteWithoutPassword('account-uuid', 'Ada', 'ada@example.com', new DateTimeImmutable),
    ]);

    it('restores as disabled when no status is given', function () {
        $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable);

        expect($account->twoFactorStatus())->toBe(TwoFactorStatus::Disabled)
            ->and($account->requiresSecondFactor())->toBeFalse();
    });

    it('restores the stored status and requires a second factor only when it is enabled', function (TwoFactorStatus $status, bool $requires) {
        $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable, twoFactorStatus: $status);

        expect($account->twoFactorStatus())->toBe($status)
            ->and($account->requiresSecondFactor())->toBe($requires);
    })->with([
        'disabled' => [TwoFactorStatus::Disabled, false],
        'pending confirmation' => [TwoFactorStatus::Pending, false],
        'enabled' => [TwoFactorStatus::Enabled, true],
    ]);

    it('still requires the second factor of an account scheduled for deletion', function () {
        $account = Account::restore(
            'account-uuid',
            'Ada',
            'ada@example.com',
            null,
            new DateTimeImmutable,
            PasswordStatus::Chosen,
            deletionRequestedAt: new DateTimeImmutable('2025-12-20T09:15:00+00:00'),
            twoFactorStatus: TwoFactorStatus::Enabled,
        );

        $account->reactivate();

        expect($account->requiresSecondFactor())->toBeTrue();
    });
});

it('never exposes a password hash', function () {
    $properties = array_map(
        static fn (ReflectionProperty $property): string => $property->getName(),
        (new ReflectionClass(Account::class))->getProperties(),
    );

    expect($properties)->not->toContain('password')
        ->and($properties)->not->toContain('passwordHash');
});
