<?php

declare(strict_types=1);

use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\InvalidAccountEmail;
use App\Domains\Accounts\Exceptions\InvalidAccountName;

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

it('never exposes a password hash', function () {
    $properties = array_map(
        static fn (ReflectionProperty $property): string => $property->getName(),
        (new ReflectionClass(Account::class))->getProperties(),
    );

    expect($properties)->not->toContain('password')
        ->and($properties)->not->toContain('passwordHash');
});
