<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\ProvisionInvitedAccountInput;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\InvalidAccountEmail;
use App\Domains\Accounts\Exceptions\InvalidAccountName;
use App\Shared\Contracts\DomainFailure;

function invitedAccountEmailOfLength(int $length): string
{
    $domain = '@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.com';

    return str_repeat('a', $length - mb_strlen($domain)).$domain;
}

it('trims the name', function () {
    expect((new ProvisionInvitedAccountInput("  Ada Lovelace\n", 'ada@example.com'))->name)->toBe('Ada Lovelace');
});

it('trims and lowercases the email so one address is looked up one way', function () {
    expect((new ProvisionInvitedAccountInput('Ada', "  Ada@Example.COM\t"))->email)->toBe('ada@example.com');
});

it('accepts a well formed payload', function () {
    expect(fn () => (new ProvisionInvitedAccountInput('Ada Lovelace', 'ada@example.com'))->validate())
        ->not->toThrow(Throwable::class);
});

it('keeps accents and non-latin characters in the name', function (string $name) {
    $input = new ProvisionInvitedAccountInput($name, 'ada@example.com');

    expect(fn () => $input->validate())->not->toThrow(Throwable::class)
        ->and($input->name)->toBe($name);
})->with([
    'accents' => 'José Álvarez Muñoz',
    'cyrillic' => 'Ада Лавлейс',
    'cjk' => '愛田 明日香',
]);

it('rejects a payload it cannot provision an account from', function (string $name, string $email, string $exception, string $message) {
    expect(fn () => (new ProvisionInvitedAccountInput($name, $email))->validate())
        ->toThrow($exception, $message);
})->with([
    'empty name' => ['', 'ada@example.com', InvalidAccountName::class, 'An account name cannot be empty.'],
    'whitespace name' => [" \t\n", 'ada@example.com', InvalidAccountName::class, 'An account name cannot be empty.'],
    'name past the limit' => [str_repeat('a', Account::MAXIMUM_NAME_LENGTH + 1), 'ada@example.com', InvalidAccountName::class, 'An account name takes up to [255] characters.'],
    'empty email' => ['Ada', '', InvalidAccountEmail::class, 'An account email cannot be empty.'],
    'whitespace email' => ['Ada', '   ', InvalidAccountEmail::class, 'An account email cannot be empty.'],
    'email past the limit' => ['Ada', invitedAccountEmailOfLength(256), InvalidAccountEmail::class, 'An account email takes up to [255] characters.'],
    'no at sign' => ['Ada', 'ada.example.com', InvalidAccountEmail::class, '[ada.example.com] is not a valid email address.'],
    'no domain' => ['Ada', 'ada@', InvalidAccountEmail::class, '[ada@] is not a valid email address.'],
    'spaces inside' => ['Ada', 'ada lovelace@example.com', InvalidAccountEmail::class, '[ada lovelace@example.com] is not a valid email address.'],
]);

it('refuses with a domain failure the use case can return', function (string $name, string $email) {
    try {
        (new ProvisionInvitedAccountInput($name, $email))->validate();
    } catch (Throwable $refusal) {
        expect($refusal)->toBeInstanceOf(DomainFailure::class);

        return;
    }

    throw new RuntimeException('The payload was accepted.');
})->with([
    'bad name' => ['', 'ada@example.com'],
    'bad email' => ['Ada', 'not-an-email'],
]);

it('checks the name before the email, reporting the first thing that is wrong', function () {
    expect(fn () => (new ProvisionInvitedAccountInput('', 'not-an-email'))->validate())
        ->toThrow(InvalidAccountName::class);
});

it('accepts a name at exactly the limit, counting characters rather than bytes', function () {
    expect(fn () => (new ProvisionInvitedAccountInput(str_repeat('ñ', Account::MAXIMUM_NAME_LENGTH), 'ada@example.com'))->validate())
        ->not->toThrow(Throwable::class);
});

it('accepts an email at exactly the limit', function () {
    $email = invitedAccountEmailOfLength(255);

    expect(mb_strlen($email))->toBe(255)
        ->and(fn () => (new ProvisionInvitedAccountInput('Ada', $email))->validate())->not->toThrow(Throwable::class);
});

it('measures the name after trimming it', function () {
    $name = '   '.str_repeat('a', Account::MAXIMUM_NAME_LENGTH).'   ';

    expect(fn () => (new ProvisionInvitedAccountInput($name, 'ada@example.com'))->validate())
        ->not->toThrow(Throwable::class);
});
